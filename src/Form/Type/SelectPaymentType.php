<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Form\Type;

use Sylius\Bundle\CoreBundle\Form\Type\Checkout\PaymentType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\FormBuilderInterface;

final class SelectPaymentType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('payments', ChangePaymentMethodType::class, [
            'entry_type' => PaymentType::class,
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'sylius_checkout_select_payment';
    }
}
