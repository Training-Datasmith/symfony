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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\Stopwatch_Event;
use Twig\Extension\Profiler_Extension as BaseProfilerExtension;
use Twig\Profiler\Profile;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Profiler_Extension extends Base_Profiler_Extension
{
    /**
     * @var \SplObjectStorage<Profile, StopwatchEvent>
     */
    private \Spl_Object_Storage $events;
    public function __construct(Profile $profile, private readonly ?Stopwatch $stopwatch = null)
    {
        parent::__construct($profile);
        $this->events = new \Spl_Object_Storage();
    }
    public function enter(Profile $profile): void
    {
        if ($this->stopwatch && $profile->is_template()) {
            $this->events[$profile] = $this->stopwatch->start($profile->get_name(), 'template');
        }
        parent::enter($profile);
    }
    public function leave(Profile $profile): void
    {
        parent::leave($profile);
        if ($this->stopwatch && $profile->is_template()) {
            $this->events[$profile]->stop();
            unset($this->events[$profile]);
        }
    }
}