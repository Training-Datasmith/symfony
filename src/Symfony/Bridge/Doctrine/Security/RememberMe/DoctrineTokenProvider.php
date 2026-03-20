<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Bridge\Doctrine\Security\Remember_Me;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Parameter_Type;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\Unqualified_Name;
use Doctrine\DBAL\Schema\Primary_Key_Constraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\Authentication\Remember_Me\Persistent_Token;
use Symfony\Component\Security\Core\Authentication\Remember_Me\Persistent_Token_Interface;
use Symfony\Component\Security\Core\Authentication\Remember_Me\Token_Provider_Interface;
use Symfony\Component\Security\Core\Authentication\Remember_Me\Token_Verifier_Interface;
use Symfony\Component\Security\Core\Exception\Token_Not_Found_Exception;
/**
 * This class provides storage for the tokens that is set in "remember-me"
 * cookies. This way no password secrets will be stored in the cookies on
 * the client machine, and thus the security is improved.
 *
 * This depends only on doctrine in order to get a database connection
 * and to do the conversion of the datetime column.
 *
 * In order to use this class, you need the following table in your database:
 *
 *     CREATE TABLE `rememberme_token` (
 *         `series`   char(88)     UNIQUE PRIMARY KEY NOT NULL,
 *         `value`    char(88)     NOT NULL,
 *         `lastUsed` datetime     NOT NULL,
 *         `class`    varchar(100) DEFAULT '' NOT NULL,
 *         `username` varchar(200) NOT NULL
 *     );
 *
 * (the `class` column is for BC with tables created with before Symfony 8)
 */
final readonly class Doctrine_Token_Provider implements Token_Provider_Interface, Token_Verifier_Interface
{
    public function __construct(private Connection $conn)
    {
    }
    public function load_token_by_series(string $series): Persistent_Token_Interface
    {
        $sql = 'SELECT class, username, value, lastUsed FROM rememberme_token WHERE series=:series';
        $param_values = ['series' => $series];
        $param_types = ['series' => Parameter_Type::STRING];
        $stmt = $this->conn->execute_query($sql, $param_values, $param_types);
        // fetching numeric because column name casing depends on platform, eg. Oracle converts all not quoted names to uppercase
        $row = $stmt->fetch_numeric() ?: throw new Token_Not_Found_Exception('No token found.');
        [$class, $username, $value, $last_used] = $row;
        if (method_exists(Persistent_Token::class, 'getClass')) {
            return new Persistent_Token($class, $username, $series, $value, new \DateTimeImmutable($last_used), false);
        }
        return new Persistent_Token($username, $series, $value, new \DateTimeImmutable($last_used));
    }
    public function delete_token_by_series(string $series): void
    {
        $sql = 'DELETE FROM rememberme_token WHERE series=:series';
        $param_values = ['series' => $series];
        $param_types = ['series' => Parameter_Type::STRING];
        $this->conn->execute_statement($sql, $param_values, $param_types);
    }
    public function update_token(
        string $series,
        #[\Sensitive_Parameter]
        string $token_value,
        \DateTimeInterface $last_used
    ): void
    {
        $sql = 'UPDATE rememberme_token SET value=:value, lastUsed=:lastUsed WHERE series=:series';
        $param_values = ['value' => $token_value, 'lastUsed' => \DateTimeImmutable::create_from_interface($last_used), 'series' => $series];
        $param_types = ['value' => Parameter_Type::STRING, 'lastUsed' => Types::DATETIME_IMMUTABLE, 'series' => Parameter_Type::STRING];
        $updated = $this->conn->execute_statement($sql, $param_values, $param_types);
        if ($updated < 1) {
            throw new Token_Not_Found_Exception('No token found.');
        }
    }
    public function create_new_token(Persistent_Token_Interface $token): void
    {
        $sql = 'INSERT INTO rememberme_token (class, username, series, value, lastUsed) VALUES (:class, :username, :series, :value, :lastUsed)';
        $param_values = ['class' => method_exists($token, 'getClass') ? $token->get_class(false) : '', 'username' => $token->get_user_identifier(), 'series' => $token->get_series(), 'value' => $token->get_token_value(), 'lastUsed' => \DateTimeImmutable::create_from_interface($token->get_last_used())];
        $param_types = ['class' => Parameter_Type::STRING, 'username' => Parameter_Type::STRING, 'series' => Parameter_Type::STRING, 'value' => Parameter_Type::STRING, 'lastUsed' => Types::DATETIME_IMMUTABLE];
        $this->conn->execute_statement($sql, $param_values, $param_types);
    }
    public function verify_token(
        Persistent_Token_Interface $token,
        #[\Sensitive_Parameter]
        string $token_value
    ): bool
    {
        // Check if the token value matches the current persisted token
        if (hash_equals($token->get_token_value(), $token_value)) {
            return true;
        }
        // Generate an alternative series id here by changing the suffix == to _
        // this is needed to be able to store an older token value in the database
        // which has a PRIMARY(series), and it works as long as series ids are
        // generated using base64_encode(random_bytes(64)) which always outputs
        // a == suffix, but if it should not work for some reason we abort
        // for safety
        $tmp_series = preg_replace('{=+$}', '_', $token->get_series());
        if ($tmp_series === $token->get_series()) {
            return false;
        }
        // Check if the previous token is present. If the given $tokenValue
        // matches the previous token (and it is outdated by at most 60seconds)
        // we also accept it as a valid value.
        try {
            $tmp_token = $this->load_token_by_series($tmp_series);
        } catch (Token_Not_Found_Exception) {
            return false;
        }
        if ($tmp_token->get_last_used()->get_timestamp() + 60 < time()) {
            return false;
        }
        return hash_equals($tmp_token->get_token_value(), $token_value);
    }
    public function update_existing_token(
        Persistent_Token_Interface $token,
        #[\Sensitive_Parameter]
        string $token_value,
        \DateTimeInterface $last_used
    ): void
    {
        if (!$token instanceof Persistent_Token) {
            return;
        }
        // Persist a copy of the previous token for authentication
        // in verifyToken should the old token still be sent by the browser
        // in a request concurrent to the one that did this token update
        $tmp_series = preg_replace('{=+$}', '_', $token->get_series());
        // if we cannot generate a unique series it is not worth trying further
        if ($tmp_series === $token->get_series()) {
            return;
        }
        $this->conn->begin_transaction();
        try {
            $this->delete_token_by_series($tmp_series);
            $last_used = \DateTime::create_from_interface($last_used);
            if (method_exists(Persistent_Token::class, 'getClass')) {
                $persistent_token = new Persistent_Token($token->get_class(false), $token->get_user_identifier(), $tmp_series, $token->get_token_value(), $last_used, false);
            } else {
                $persistent_token = new Persistent_Token($token->get_user_identifier(), $tmp_series, $token->get_token_value(), $last_used);
            }
            $this->create_new_token($persistent_token);
            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->roll_back();
            throw $e;
        }
    }
    /**
     * Adds the Table to the Schema if "remember me" uses this Connection.
     */
    public function configure_schema(Schema $schema, Connection $for_connection, \Closure $is_same_database): void
    {
        if ($schema->has_table('rememberme_token')) {
            return;
        }
        if ($for_connection !== $this->conn && !$is_same_database($this->conn->execute_statement(...))) {
            return;
        }
        $this->add_table_to_schema($schema);
    }
    private function add_table_to_schema(Schema $schema): void
    {
        $table = $schema->create_table('rememberme_token');
        $table->add_column('series', Types::STRING, ['length' => 88]);
        $table->add_column('value', Types::STRING, ['length' => 88]);
        $table->add_column('lastUsed', Types::DATETIME_IMMUTABLE);
        $table->add_column('class', Types::STRING, ['length' => 100, 'default' => '']);
        $table->add_column('username', Types::STRING, ['length' => 200]);
        $table->add_primary_key_constraint(new Primary_Key_Constraint(null, [new Unqualified_Name(Identifier::unquoted('series'))], true));
    }
}