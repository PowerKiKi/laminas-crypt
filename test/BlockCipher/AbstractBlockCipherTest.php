<?php

/**
 * @see       https://github.com/laminas/laminas-crypt for the canonical source repository
 * @copyright https://github.com/laminas/laminas-crypt/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-crypt/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Crypt\BlockCipher;

use Interop\Container\ContainerInterface;
use Laminas\Crypt\BlockCipher;
use Laminas\Crypt\Exception as CryptException;
use Laminas\Crypt\Symmetric;
use PHPUnit_Framework_TestCase as TestCase;

abstract class AbstractBlockCipherTest extends TestCase
{
    /**
     * @var Symmetric\SymmetricInterface
     */
    protected $cipher;

    /**
     * @var BlockCipher
     */
    protected $blockCipher;

    /**
     * @var string
     */
    protected $plaintext;

    public function setUp()
    {
        $this->assertInstanceOf(
            Symmetric\SymmetricInterface::class,
            $this->cipher,
            'Symmetric adapter instance is needed for tests'
        );
        $this->blockCipher = new BlockCipher($this->cipher);
        $this->plaintext = file_get_contents(__DIR__ . '/../_files/plaintext');
    }

    public function testSetKey()
    {
        $result = $this->blockCipher->setKey('test');
        $this->assertEquals($result, $this->blockCipher);
        $this->assertEquals('test', $this->blockCipher->getKey());
    }

    public function testSetSalt()
    {
        $salt = str_repeat('a', $this->blockCipher->getCipher()->getSaltSize() + 2);
        $result = $this->blockCipher->setSalt($salt);
        $this->assertEquals($result, $this->blockCipher);
        $this->assertEquals(
            substr($salt, 0, $this->blockCipher->getCipher()->getSaltSize()),
            $this->blockCipher->getSalt()
        );
        $this->assertEquals($salt, $this->blockCipher->getOriginalSalt());
    }

    public function testSetAlgorithm()
    {
        $result = $this->blockCipher->setCipherAlgorithm('blowfish');
        $this->assertEquals($result, $this->blockCipher);
        $this->assertEquals('blowfish', $this->blockCipher->getCipherAlgorithm());
    }

    public function testSetAlgorithmFail()
    {
        $this->setExpectedException(
            CryptException\InvalidArgumentException::class,
            sprintf('The algorithm unknown is not supported by %s', get_class($this->cipher))
        );
        $result = $this->blockCipher->setCipherAlgorithm('unknown');
    }

    public function testSetHashAlgorithm()
    {
        $result = $this->blockCipher->setHashAlgorithm('sha1');
        $this->assertEquals($result, $this->blockCipher);
        $this->assertEquals('sha1', $this->blockCipher->getHashAlgorithm());
    }

    public function testSetPbkdf2HashAlgorithm()
    {
        $result = $this->blockCipher->setPbkdf2HashAlgorithm('sha1');
        $this->assertEquals($result, $this->blockCipher);
        $this->assertEquals('sha1', $this->blockCipher->getPbkdf2HashAlgorithm());
    }

    public function testSetKeyIteration()
    {
        $result = $this->blockCipher->setKeyIteration(1000);
        $this->assertEquals($result, $this->blockCipher);
        $this->assertEquals(1000, $this->blockCipher->getKeyIteration());
    }

    public function testEncryptWithoutData()
    {
        $plaintext = '';
        $this->setExpectedException(
            CryptException\InvalidArgumentException::class,
            'The data to encrypt cannot be empty'
        );
        $ciphertext = $this->blockCipher->encrypt($plaintext);
    }

    public function testEncryptErrorKey()
    {
        $plaintext = 'test';
        $this->setExpectedException(
            CryptException\InvalidArgumentException::class,
            'No key specified for the encryption'
        );
        $ciphertext = $this->blockCipher->encrypt($plaintext);
    }

    public function testEncryptDecrypt()
    {
        $this->blockCipher->setKey('test');
        $this->blockCipher->setKeyIteration(1000);
        foreach ($this->blockCipher->getCipherSupportedAlgorithms() as $algo) {
            $this->blockCipher->setCipherAlgorithm($algo);
            $encrypted = $this->blockCipher->encrypt($this->plaintext);
            $this->assertNotEmpty($encrypted);
            $decrypted = $this->blockCipher->decrypt($encrypted);
            $this->assertEquals($decrypted, $this->plaintext);
        }
    }

    public function testEncryptDecryptUsingBinary()
    {
        $this->blockCipher->setKey('test');
        $this->blockCipher->setKeyIteration(1000);
        $this->blockCipher->setBinaryOutput(true);
        foreach ($this->blockCipher->getCipherSupportedAlgorithms() as $algo) {
            $this->blockCipher->setCipherAlgorithm($algo);
            $encrypted = $this->blockCipher->encrypt($this->plaintext);
            $this->assertNotEmpty($encrypted);
            $decrypted = $this->blockCipher->decrypt($encrypted);
            $this->assertEquals($decrypted, $this->plaintext);
        }
    }

    public function zeroValuesProvider()
    {
        return [
            '"0"'   => [0],
            '"0.0"' => [0.0],
            '"0"'   => ['0'],
        ];
    }

    /**
     * @dataProvider zeroValuesProvider
     */
    public function testEncryptDecryptUsingZero($value)
    {
        $this->blockCipher->setKey('test');
        $this->blockCipher->setKeyIteration(1000);
        foreach ($this->blockCipher->getCipherSupportedAlgorithms() as $algo) {
            $this->blockCipher->setCipherAlgorithm($algo);

            $encrypted = $this->blockCipher->encrypt($value);
            $this->assertNotEmpty($encrypted);
            $decrypted = $this->blockCipher->decrypt($encrypted);
            $this->assertEquals($value, $decrypted);
        }
    }

    public function testDecryptAuthFail()
    {
        $this->blockCipher->setKey('test');
        $this->blockCipher->setKeyIteration(1000);
        $encrypted = $this->blockCipher->encrypt($this->plaintext);
        $this->assertNotEmpty($encrypted);
        // tamper the encrypted data
        $encrypted = substr($encrypted, -1);
        $decrypted = $this->blockCipher->decrypt($encrypted);
        $this->assertFalse($decrypted);
    }

    public function testSetSymmetricPluginManager()
    {
        $old = $this->blockCipher->getSymmetricPluginManager();

        $this->blockCipher->setSymmetricPluginManager(
            $this->getMockBuilder(ContainerInterface::class)->getMock()
        );
        $this->assertInstanceOf(ContainerInterface::class, $this->blockCipher->getSymmetricPluginManager());

        $this->blockCipher->setSymmetricPluginManager($old);
    }
}
