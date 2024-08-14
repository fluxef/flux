<?php
/** @noinspection PhpParamsInspection */
declare(strict_types=1);

namespace Flux\Crypto\Tests;

use Exception;
use Flux\Crypto\aead;
use PHPUnit\Framework\TestCase;

class aeadTest extends TestCase
{


    public function testCryptDecrypt()
    {
        $pass = 'lxckjhselrkjhsdflkjhbsdkjh';

        $key = aead::createKey($pass);

        $data = array('bla' => 'eineineins', 'fasel' => 4711);

        $b64cipher = aead::ArrayEncryptBase64($data, $key);

        $newdata = aead::ArrayDecryptBase64($b64cipher, $key);

        $this->assertEquals($data, $newdata);

    }


    public function testDecrypt()
    {
        $pass = 'lxckjhselrkjhsdflkjhbsdkjh';
        $b64reference = 'B9gJeLG4/IUSFnqGf7QpI74+EnwhjobmsDtb5papRHa9uNgbwrz6j7IKdzWH20/ZdgfkEDrki1s+lHue3+8He8xnbQVj2TXClA==';
        $data = array('bla' => 'eineineins', 'fasel' => 4711);

        $key = aead::createKey($pass);

        $newdata = aead::ArrayDecryptBase64($b64reference, $key);

        $this->assertEquals($data, $newdata);

    }

    public function testCryptDecryptWrongPassphrase()
    {
        $pass = 'lxckjhselrkjhsdflkjhbsdkjh';

        $key = aead::createKey($pass);

        $data = array('bla' => 'eineineins', 'fasel' => 4711);

        $b64cipher = aead::ArrayEncryptBase64($data, $key);

        $key = aead::createKey($pass . 'bla');
        $this->expectException(Exception::class);
        $newdata = aead::ArrayDecryptBase64($b64cipher, $key);

        $this->assertNotEquals($data, $newdata);

    }

}
