<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use App\Traits\DemoDataTestTrait;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker, DemoDataTestTrait;

    public function test_create_order_validation_fail()
    {
        $this->basicSeeding();
        $cartId = $this->getIdNewCart();

        $response = $this->disableCookieEncryption()->withHeaders([
                            'accept' => 'application/json'
                        ])->withCookie(config('constants.cookie_name.cart'), $cartId)
                        ->post('/api/v1/orders', [
                            'payment_method' => 'bank jateng',
                            'same_as_shipping_address' => 0
                        ]);

        $response->assertStatus(422)
                ->assertJson(fn (AssertableJson $json) =>
                    $json->hasAll(['message','errors'])
                );
    }

    /**
     * @dataProvider paymentMethodData
     */
    public function test_create_order_success(array $formData, $expectedResponse)
    {
        $this->basicSeeding();
        $cartId = $this->getIdNewCart();
        $this->createShippingInformation([
            'shippingable_id' => $cartId
        ]);

        $response = $this->disableCookieEncryption()->withHeaders([
                            'accept' => 'application/json'
                        ])->withCookie(config('constants.cookie_name.cart'), $cartId)
                        ->post('/api/v1/orders', $formData);

        $response->assertStatus(200)
                ->assertJson($expectedResponse);
    }

    public function paymentMethodData(): array
    {
        $billingAddress = [
            'same_as_shipping_address' => 0,
            'first_name' => 'Imam',
            'last_name' => 'Setiawan',
            'phone' => '03144141',
            'address' => 'Wungusari',
            'city' => 'Bandung',
            'state' => 'Jawa Barat',
            'postcode' => '52121'
        ];

        $basePaymentDetails = [
            'transaction_id', 'transaction_time', 'payment_type', 'bank', 'gross_amount', 'message', 'fraud_status', 'order_id'
        ];

        // [array $formData, $expectedResponse]
        return [
                [
                    array_merge($billingAddress, ['payment_method' => 'bni']), 
                    fn (AssertableJson $json) =>
                        $json->has('payment_detail', fn ($json) => 
                            $json->hasAll($basePaymentDetails)
                                ->hasAll(['va_number'])
                                ->where('payment_type', 'bank_transfer')
                                ->where('bank', 'bni')
                                ->etc()
                        )
                ],
                [
                    array_merge($billingAddress, ['payment_method' => 'mandiri']),
                    fn (AssertableJson $json) =>
                        $json->has('payment_detail', fn ($json) => 
                            $json->hasAll($basePaymentDetails)
                                ->hasAll(['bill_key', 'biller_code'])
                                ->where('payment_type', 'echannel')
                                ->where('bank', 'mandiri')
                                ->etc()
                        )
                ],
                [
                    array_merge($billingAddress, ['payment_method' => 'permata']),
                    fn (AssertableJson $json) =>
                        $json->has('payment_detail', fn ($json) => 
                            $json->hasAll($basePaymentDetails)
                                ->hasAll(['va_number'])
                                ->where('payment_type', 'bank_transfer')
                                ->where('bank', 'permata')
                                ->etc()
                        )
                ],
            ];
    }

    public function test_get_order_detail_success()
    {
        $this->generateAccessToken();
        $this->basicSeeding();
        $orderId = $this->getIdNewOrder();

        $response = $this->getJson('/api/v1/orders/'.$orderId);

        $response->assertStatus(200)
                ->assertJson(fn (AssertableJson $json) =>
                    $json->has('data', fn ($json) => 
                        $json->hasAll([
                            'status',
                            'order_date',
                            'total_price',
                            'total_weight',
                            'total_payment'
                        ])
                        ->has('order_items', 2, fn ($json) =>
                            $json->hasAll([
                                'product_id',
                                'product_name',
                                'product_featured_image_url',
                                'product_quantity',
                                'product_price',
                                'subtotal_price',
                            ])
                        )
                        ->has('shipping_info', fn ($json) =>
                            $json->hasAll([
                                'courier',
                                'fullname',
                                'phone',
                                'address',
                                'shipping_cost'
                            ])
                        )
                        ->etc()
                    )->etc()
                );
    }
}
