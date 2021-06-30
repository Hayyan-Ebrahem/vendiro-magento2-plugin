<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Vendiro\Test\Unit\Service\Order;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Model\StockState;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use TIG\Vendiro\Service\Order\Product;
use TIG\Vendiro\Test\TestCase;

class ProductTest extends TestCase
{
    protected $instanceClass = Product::class;

    public function testCreateProductDataFromApiData()
    {
        $apiData = [
            'company' => 'TIG',
            'amount' => '2',
            'status' => 'new',
            'value' => '3.45',
            'sku' => 'TIG123'
        ];

        $expectedResult = [
            'qty' => 2,
            'custom_price' => '3.45'
        ];

        $dataObjectMock = $this->getFakeMock(DataObjectFactory::class)->setMethods(null)->getMock();

        $instance = $this->getInstance(['dataObjectFactory' => $dataObjectMock]);
        $result = $instance->createProductDataFromApiData($apiData);

        $this->assertInstanceOf(DataObject::class, $result);
        $this->assertEquals($expectedResult, $result->getData());
    }

    /**
     * @return array
     */
    public function getBySkuProvider()
    {
        return [
            'product and stock found' => [
                987,
                10,
                null
            ],
            'product id not found' => [
                false,
                15,
                'The order could not be imported. The requested product SKU 123456 wasn\'t found.'
            ]
        ];
    }

    /**
     * @param $productId
     * @param $qty
     * @param $exceptionMessage
     *
     * @throws \Exception
     * @dataProvider getBySkuProvider
     */
    public function testGetBySku($productId, $qty, $exceptionMessage)
    {
        if ($exceptionMessage !== null) {
            $this->expectException(\TIG\Vendiro\Exception::class);
            $this->expectExceptionMessage($exceptionMessage);
        }

        $sku = '123456';

        $productMock = $this->getFakeMock(ProductInterface::class)->setMethods(['getId'])->getMockForAbstractClass();
        $productMock->method('getId')->willReturn($productId);

        $productRepoMock = $this->getFakeMock(ProductRepositoryInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();
        $productRepoMock->expects($this->once())->method('get')->with($sku)->willReturn($productMock);

        $stockStateMock = $this->getFakeMock(StockState::class)->setMethods(['getStockQty'])->getMock();
        $stockStateMock->method('getStockQty')->with($productId)->willReturn($qty);

        $instance = $this->getInstance(['productRepository' => $productRepoMock, 'stockState' => $stockStateMock]);
        $result = $instance->getBySku($sku);

        if ($exceptionMessage === null) {
            $this->assertEquals($productMock, $result);
        }
    }

    public function testLoadProductThrowsException()
    {
        $this->expectException(\TIG\Vendiro\Exception::class);
        $this->expectExceptionMessage("The order could not be imported. The requested product SKU sku12 wasn't found.");

        $productRepoMock = $this->getFakeMock(ProductRepositoryInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();
        $productRepoMock->method('get')->willThrowException(new NoSuchEntityException(__('Product not found')));

        $instance = $this->getInstance(['productRepository' => $productRepoMock]);
        $this->invokeArgs('loadProduct', ['sku12'], $instance);
    }
}
