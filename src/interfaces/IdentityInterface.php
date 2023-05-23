<?php
namespace safepartner\tfa\interfaces;

/**
 * IdentityInterface is the interface that should be implemented by a class providing identity support tfa.
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
interface IdentityInterface extends \yii\web\IdentityInterface
{

    public function isTfaEmailEnabled();

    public function getEmail();

    public function isTfaSMSEnabled();

    public function getPhone();
	
	public function isTfaNeedCookieReset();

	public function setTfaNeedCookieReset($reset);	
}
