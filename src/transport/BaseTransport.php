<?php
namespace safepartner\tfa\transport;

/**
 * BaseTransport class
 * 
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
abstract class BaseTransport extends \yii\base\BaseObject implements TransportInterface
{

    /**
     * Message subject
     * @var string
     */
    public $subject = 'Authentication code';

    /**
     * Message body
     * @var string
     */
    public $message = 'Your authentication code {code}';

    /**
     * Error message
     * @var string
     */
    public $error = '';

    /**
     * @inheritdoc
     */
    public function getError(): string
    {
        return $this->error;
    }
}
