<?php
namespace safepartner\tfa\transport;

use safepartner\tfa\interfaces\IdentityInterface;
use Yii;
use const PHP_EOL;

/**
 * Email Based OTP (One Time Password) Transport
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class FileTransport extends BaseTransport
{

    public $fileTransportPath = '@runtime/tfa';

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return 'file';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Fail';
    }

    /**
     * @inheritdoc
     */
    public function obfuscateTo(IdentityInterface $identity): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function sendOneTimePassword(string $otp, IdentityInterface $identity): bool
    {
        $message = sprintf(
            '%s' . PHP_EOL .
            'To: email:%s, phone:%s' . PHP_EOL .
            'Subject: %s' . PHP_EOL .
            'Message: %s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            $identity->getEmail(), $identity->getPhone(),
            Yii::t('app', $this->subject),
            Yii::t('app', $this->message, ['code' => $otp])
        );

        $this->saveMessage($message);

        return true;
    }

    /**
     * Saves the message as a file under [[fileTransportPath]].
     * @param string $message
     * @return bool whether the message is saved successfully
     */
    protected function saveMessage($message)
    {
        $path = Yii::getAlias($this->fileTransportPath);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $file = $path . '/' . $this->generateFileName();

        return file_put_contents($file, $message) !== false;
    }

    /**
     * @return string the file name for saving the tfa message
     */
    protected function generateFileName()
    {
        $time = microtime(true);

        return date('Ymd-His-', $time) . sprintf('%04d', (int) (($time - (int) $time) * 10000)) . '-' . sprintf('%04d', mt_rand(0, 10000)) . '.txt';
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(IdentityInterface $identity): bool
    {
        return true;
    }
}
