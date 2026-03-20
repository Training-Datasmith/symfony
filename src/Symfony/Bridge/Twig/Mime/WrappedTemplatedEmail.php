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
namespace Symfony\Bridge\Twig\Mime;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\Data_Part;
use Symfony\Component\Mime\Part\File;
use Twig\Environment;
/**
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final readonly class Wrapped_Templated_Email
{
    public function __construct(private Environment $twig, private Templated_Email $message)
    {
    }
    public function to_name(): string
    {
        return $this->message->get_to()[0]->get_name();
    }
    /**
     * @param string      $image       A Twig path to the image file. It's recommended to define
     *                                 some Twig namespace for email images (e.g. '@email/images/logo.png').
     * @param string|null $contentType The media type (i.e. MIME type) of the image file (e.g. 'image/png').
     *                                 Some email clients require this to display embedded images.
     * @param string|null $name        A custom file name that overrides the original name (filepath) of the image
     */
    public function image(string $image, ?string $content_type = null, ?string $name = null): string
    {
        $file = $this->twig->get_loader()->get_source_context($image);
        $body = $file->get_path() ? new File($file->get_path()) : $file->get_code();
        $name = $name ?: $image;
        $this->message->add_part((new Data_Part($body, $name, $content_type))->as_inline());
        return 'cid:' . $name;
    }
    /**
     * @param string      $file        A Twig path to the file. It's recommended to define
     *                                 some Twig namespace for email files (e.g. '@email/files/contract.pdf').
     * @param string|null $name        A custom file name that overrides the original name of the attached file
     * @param string|null $contentType The media type (i.e. MIME type) of the file (e.g. 'application/pdf').
     *                                 Some email clients require this to display attached files.
     */
    public function attach(string $file, ?string $name = null, ?string $content_type = null): void
    {
        $file = $this->twig->get_loader()->get_source_context($file);
        $body = $file->get_path() ? new File($file->get_path()) : $file->get_code();
        $this->message->add_part(new Data_Part($body, $name, $content_type));
    }
    /**
     * @return $this
     */
    public function set_subject(string $subject): static
    {
        $this->message->subject($subject);
        return $this;
    }
    public function get_subject(): ?string
    {
        return $this->message->get_subject();
    }
    /**
     * @return $this
     */
    public function set_return_path(string $address): static
    {
        $this->message->return_path($address);
        return $this;
    }
    public function get_return_path(): string
    {
        return $this->message->get_return_path()?->to_string() ?? '';
    }
    /**
     * @return $this
     */
    public function add_from(string $address, string $name = ''): static
    {
        $this->message->add_from(new Address($address, $name));
        return $this;
    }
    /**
     * @return Address[]
     */
    public function get_from(): array
    {
        return $this->message->get_from();
    }
    /**
     * @return $this
     */
    public function add_reply_to(string $address): static
    {
        $this->message->add_reply_to($address);
        return $this;
    }
    /**
     * @return Address[]
     */
    public function get_reply_to(): array
    {
        return $this->message->get_reply_to();
    }
    /**
     * @return $this
     */
    public function add_to(string $address, string $name = ''): static
    {
        $this->message->add_to(new Address($address, $name));
        return $this;
    }
    /**
     * @return Address[]
     */
    public function get_to(): array
    {
        return $this->message->get_to();
    }
    /**
     * @return $this
     */
    public function add_cc(string $address, string $name = ''): static
    {
        $this->message->add_cc(new Address($address, $name));
        return $this;
    }
    /**
     * @return Address[]
     */
    public function get_cc(): array
    {
        return $this->message->get_cc();
    }
    /**
     * @return $this
     */
    public function add_bcc(string $address, string $name = ''): static
    {
        $this->message->add_bcc(new Address($address, $name));
        return $this;
    }
    /**
     * @return Address[]
     */
    public function get_bcc(): array
    {
        return $this->message->get_bcc();
    }
    /**
     * @return $this
     */
    public function set_priority(int $priority): static
    {
        $this->message->priority($priority);
        return $this;
    }
    public function get_priority(): int
    {
        return $this->message->get_priority();
    }
}