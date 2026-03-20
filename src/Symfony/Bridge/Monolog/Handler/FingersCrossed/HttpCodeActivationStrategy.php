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
namespace Symfony\Bridge\Monolog\Handler\Fingers_Crossed;

use Monolog\Handler\Fingers_Crossed\Activation_Strategy_Interface;
use Monolog\Log_Record;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Kernel\Exception\Http_Exception_Interface;
/**
 * Activation strategy that ignores certain HTTP codes.
 *
 * @author Shaun Simmons <shaun@envysphere.com>
 * @author Pierrick Vignand <pierrick.vignand@gmail.com>
 */
final readonly class Http_Code_Activation_Strategy implements Activation_Strategy_Interface
{
    /**
     * @param array $exclusions each exclusion must have a "code" and "urls" keys
     */
    public function __construct(private Request_Stack $request_stack, private array $exclusions, private Activation_Strategy_Interface $inner)
    {
        foreach ($exclusions as $exclusion) {
            if (!\array_key_exists('code', $exclusion)) {
                throw new \LogicException('An exclusion must have a "code" key.');
            }
            if (!\array_key_exists('urls', $exclusion)) {
                throw new \LogicException('An exclusion must have a "urls" key.');
            }
        }
    }
    public function is_handler_activated(Log_Record $record): bool
    {
        $is_activated = $this->inner->is_handler_activated($record);
        if ($is_activated && isset($record->context['exception']) && $record->context['exception'] instanceof Http_Exception_Interface && $request = $this->request_stack->get_main_request()) {
            foreach ($this->exclusions as $exclusion) {
                if ($record->context['exception']->get_status_code() !== $exclusion['code']) {
                    continue;
                }
                if (\count($exclusion['urls'])) {
                    return !preg_match('{(' . implode('|', $exclusion['urls']) . ')}i', $request->get_path_info());
                }
                return false;
            }
        }
        return $is_activated;
    }
}