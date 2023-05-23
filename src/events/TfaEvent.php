<?php
namespace safepartner\tfa\events;

use safepartner\tfa\transport\TransportInterface;
use yii\base\Event;
use yii\web\IdentityInterface;

/**
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class TfaEvent extends Event
{

    /**
     * @var IdentityInterface the identity object associated with this event
     */
    public $identity;

    /**
     * @var TransportInterface the transport object associated with this event
     */
    public $transport;

    /**
     * @var string One time password associated with this event
     */
    public $otp;

    /**
     * @var bool if message was sent successfully
     */
    public $isSuccessful = false;

    /**
     * @var string error massage
     */
    public $error = '';

    /**
     * @var bool whether to continue sending an sms. Event handlers of
     * [[EVENT_BEFORE_SEND]] may set this property to decide whether
     * to continue send or not
     */
    public $isValid = true;

}
