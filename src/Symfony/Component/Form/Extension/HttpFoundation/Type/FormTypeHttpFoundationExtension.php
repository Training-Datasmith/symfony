<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HttpFoundation\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\RequestHandlerInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeHttpFoundationExtension extends AbstractTypeExtension
{
    public function __construct(private readonly ?RequestHandlerInterface $requestHandler = new HttpFoundationRequestHandler())
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setRequestHandler($this->requestHandler);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
