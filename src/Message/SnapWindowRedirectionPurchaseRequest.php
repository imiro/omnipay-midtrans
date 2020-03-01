<?php

namespace Omnipay\Midtrans\Message;


use Guzzle\Http\Exception\ClientErrorResponseException;
use Omnipay\Common\Exception\InvalidRequestException;

class SnapWindowRedirectionPurchaseRequest extends AbstractRequest
{
    const MIN_AMOUNT = 10000;
    const MAX_LENGTH_TRANSACTION_ID = 50;

    private $notificationOverrideUrls = NULL;
    private $notificationAppendUrls = NULL;

    public function setNotificationOverrideURL($urls)
    {
        $this->notificationOverrideUrls = $urls;
    }

    public function setNotificationAppendURL($urls)
    {
        $this->notificationAppendUrls = $urls;
    }

    public function sendData($data)
    {
        $responseData = $this->httpClient
            ->request('post', $this->getEndPoint(), $this->getSendDataHeader(), json_encode($data))
            ->getBody()
            ->getContents();

        return $this->response = new SnapWindowRedirectionPurchaseResponse($this, $responseData);
    }

    /**
     * @return array|mixed
     * @throws InvalidRequestException
     */
    public function getData()
    {
        $this->validate('amount', 'transactionId');

        $this->guardMinAmount();

        $this->guardTransationId();

        $result = [
            'transaction_details' => [
                'order_id' => $this->getTransactionId(),
                'gross_amount' => intval($this->getAmount()),
            ],
            'item_details' => [
                [
                    'id' => $this->getTransactionId(),
                    'price' => intval($this->getAmount()),
                    'quantity' => 1,
                    'name' => $this->getDescription(),
                    'brand' => $this->getDescription(),
                ]
            ],
            'credit_card' => [
                'secure' => true
            ]
        ];

        if ($this->getCard()) {
            $result['customer_details'] = [
                'first_name' => $this->getCard()->getFirstName(),
                'last_name' => $this->getCard()->getLastName(),
                'email' => $this->getCard()->getEmail(),
                'phone' => $this->getCard()->getNumber(),
            ];
        }

        if($this->getReturnUrl()) {
          $result['callbacks'] = [
            'finish' => $this->getReturnUrl()
          ];
        }

        return $result;
    }

    protected function getSendDataHeader()
    {
        $ret = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($this->getServerKey() . ':')
        ];

        if( !is_null( $this->notificationAppendUrls ) )
          $ret['X-Append-Notification'] = $this->notificationAppendUrls;

        if( !is_null( $this->notificationOverrideUrls ) )
          $ret['X-Override-Notification'] = $this->notificationOverrideUrls;

        return $ret;
    }

    /**
     * @throws InvalidRequestException
     */
    private function guardMinAmount()
    {
        if (intval($this->getAmount()) < self::MIN_AMOUNT) {
            throw new InvalidRequestException(
                sprintf('Each transaction requires a minimum gross_amount of %s.', self::MIN_AMOUNT)
            );
        }
    }

    /**
     * @throws InvalidRequestException
     */
    private function guardTransationId()
    {
        if (!preg_match('/^[a-z0-9\-_\~.]+$/i', $this->getTransactionId())) {
            throw new InvalidRequestException(
                'Allowed symbols for transactionId are dash(-), underscore(_), tilde (~), and dot (.)'
            );
        }

        if (strlen($this->getTransactionId()) > self::MAX_LENGTH_TRANSACTION_ID) {
            throw new InvalidRequestException(
                'Max length for transactionId is ' . self::MAX_LENGTH_TRANSACTION_ID
            );
        }

    }

}
