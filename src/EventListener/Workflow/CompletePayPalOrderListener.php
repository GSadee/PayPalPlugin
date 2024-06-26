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

namespace Sylius\PayPalPlugin\EventListener\Workflow;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Processor\PayPalOrderCompleteProcessor;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

final class CompletePayPalOrderListener
{
    public function __construct(private readonly PayPalOrderCompleteProcessor $completeProcessor)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        /** @var OrderInterface $order */
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->completeProcessor->completePayPalOrder($order);
    }
}
