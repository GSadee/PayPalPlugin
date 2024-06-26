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

namespace Sylius\PayPalPlugin\Controller;

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class PayPalPaymentOnErrorAction
{
    private FlashBagInterface|RequestStack $flashBagOrRequestStack;

    private LoggerInterface $logger;

    public function __construct(FlashBagInterface|RequestStack $flashBagOrRequestStack, LoggerInterface $logger)
    {
        if ($flashBagOrRequestStack instanceof FlashBagInterface) {
            trigger_deprecation('sylius/paypal-plugin', '1.5', sprintf('Passing an instance of %s as constructor argument for %s is deprecated as of PayPalPlugin 1.5 and will be removed in 2.0. Pass an instance of %s instead.', FlashBagInterface::class, self::class, RequestStack::class));
        }

        $this->flashBagOrRequestStack = $flashBagOrRequestStack;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        /**
         * @var string $content
         */
        $content = $request->getContent();

        $this->logger->error($content);
        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
            ->add('error', 'sylius.pay_pal.something_went_wrong')
        ;

        return new Response();
    }
}
