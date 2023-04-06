<?php declare(strict_types=1);

namespace Dne\DiscountExample\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomPromotionProcessor implements CartProcessorInterface
{
    private AbsolutePriceCalculator $calculator;

    public function __construct(AbsolutePriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $discountLineItem = $this->createDiscount('EXAMPLE_DISCOUNT');

        if ($toCalculate->getLineItems()->filterGoods()->count() < 2) {
            if ($toCalculate->has($discountLineItem->getId())) {
                $toCalculate->get($discountLineItem->getId())->setRemovable(true);
                $toCalculate->remove($discountLineItem->getId());
            }

            return;
        }

        $goods = $toCalculate->getLineItems()->filterGoods();
        $goods->sort(function (LineItem $a, LineItem $b) {
            if ($a->getPrice()->getUnitPrice() === $b->getPrice()->getUnitPrice()) {
                return 0;
            }

            if ($a->getPrice()->getUnitPrice() < $b->getPrice()->getUnitPrice()) {
                return -1;
            }

            return 1;
        });
        $cheapest = $goods->first();
        $cheapestPrice = -$cheapest->getPrice()->getUnitPrice();

        // declare price definition to define how this price is calculated
        $definition = new CurrencyPriceDefinition(new PriceCollection([
            new Price($context->getCurrencyId(), $cheapestPrice, $cheapestPrice, false),
        ]));

        $discountLineItem->setPriceDefinition($definition);
        // calculate price
        $discountLineItem->setPrice(
            $this->calculator->calculate($cheapestPrice, $goods->getPrices(), $context)
        );

        if ($original->has($discountLineItem->getId())) {
            $original->get($discountLineItem->getId())->setPriceDefinition($discountLineItem->getPriceDefinition());
            $original->get($discountLineItem->getId())->setPrice($discountLineItem->getPrice());

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
