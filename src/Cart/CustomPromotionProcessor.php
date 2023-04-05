<?php declare(strict_types=1);

namespace Dne\DiscountExample\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomPromotionProcessor implements CartProcessorInterface
{
    private PercentagePriceCalculator $calculator;

    public function __construct(PercentagePriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($toCalculate->getLineItems()->filterGoods()->count() === 0) {
            return;
        }

        $discountLineItem = $this->createDiscount('EXAMPLE_DISCOUNT');

        // declare price definition to define how this price is calculated
        $definition = new PercentagePriceDefinition(-10);

        $discountLineItem->setPriceDefinition($definition);

        // calculate price
        $discountLineItem->setPrice(
            $this->calculator->calculate($definition->getPercentage(), $toCalculate->getLineItems()->getPrices(), $context)
        );

        if ($original->has($discountLineItem->getId())) {
            $original->get($discountLineItem->getId())->setRemovable(true);
            $original->remove($discountLineItem->getId());

            return;
        }

        // add discount to new cart
        $toCalculate->add($discountLineItem);
    }

    private function createDiscount(string $name): LineItem
    {
        $discountLineItem = new LineItem($name, LineItem::DISCOUNT_LINE_ITEM, null, 1);

        $discountLineItem->setLabel('Our example discount!');
        $discountLineItem->setGood(false);
        $discountLineItem->setStackable(false);
        $discountLineItem->setRemovable(false);

        return $discountLineItem;
    }
}
