<?php

namespace NTI\EmailBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class SMTP
 * @ORM\Entity()
 * @ORM\Table(name="nti_smtp_configuration")
 * @ORM\HasLifecycleCallbacks()
 */
class Smtp {

    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="host", type="string", length=255)
     *
     * @Assert\NotBlank(message="El campo de Server / Host es obligatorio.")
     */
    private $host;

    /**
     * @var string
     *
     * @ORM\Column(name="port", type="integer")
     *
     * @Assert\NotBlank(message="El campo de Puerto es obligatorio")
     */
    private $port;

    /**
     * @var string
     *
     * @ORM\Column(name="encryption", type="string", length=255, nullable=true)
     *
     */
    private $encryption;

    /**
     * @var string
     *
     * @ORM\Column(name="user", type="string", length=255)
     *
     * @Assert\NotBlank(message="El campo de Usuario es obligatorio")
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=255)
     *
     * @Assert\NotBlank(message="El campo de Contraseña es obligatorio")
     */
    private $password;

    /**
     * @var string
     *
     * @ORM\Column(name="environment", type="string", length=255)
     *
     */
    private $environment;

    /**
     * SMTP constructor.
     */
    public function __construct() {
        $this->enabled = true;
    }

    /**
     * @return integer
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     * @return Smtp
     */
    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param string $port
     * @return Smtp
     */
    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncryption()
    {
        return $this->encryption;
    }

    /**
     * @param string $encryption
     * @return Smtp
     */
    public function setEncryption($encryption)
    {
        $this->encryption = $encryption;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param string $transport
     * @return Smtp
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $user
     * @return Smtp
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return Smtp
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @param string $environment
     * @return Smtp
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
        return $this;
    }


}
