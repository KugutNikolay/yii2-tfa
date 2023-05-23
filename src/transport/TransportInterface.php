<?php
namespace safepartner\tfa\transport;

use safepartner\tfa\interfaces\IdentityInterface;

/**
 * TransportInterface is the interface that should be implemented by a class providing OTP (One Time Password) transport
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
interface TransportInterface
{

    /**
     * Transport code
     * @return string transport code
     */
    public function getCode();

    /**
     * Transport name
     * @return string transport name
     */
    public function getName();

    /**
     * Returns the transport is enable for IdentityInterface
     * @param IdentityInterface $identity
     * @return bool is enabled
     */
    public function isEnabled(IdentityInterface $identity): bool;

    /**
     * Get Obfuscated `To`
     * @param IdentityInterface $identity from which the sending parameters will be taken
     * @return string obfuscated to
     */
    public function obfuscateTo(IdentityInterface $identity);

    /**
     * Send the OTP (One Time Password) to User.
     * Recipient/sender data will be retrieved from the IdentityInterface.
     *
     * This is the responsibility of the send method to start the transport if needed.
     *
     * @param string $otp One Time Password
     * @param IdentityInterface $identity from which the sending parameters will be taken
     *
     * @return bool whether the message has been sent successfully
     */
    public function sendOneTimePassword(string $otp, IdentityInterface $identity): bool;

    /**
     * Returns the error if send one time password fail
     * @return string the error
     */
    public function getError(): string;
}
