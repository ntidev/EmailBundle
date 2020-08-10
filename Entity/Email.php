<?php

namespace NTI\EmailBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use NTIEmailBundle\Entity\EmailAttachment;

/**
 * Email
 *
 * @ORM\Table(name="nti_email")
 * @ORM\Entity(repositoryClass="NTI\EmailBundle\Repository\EmailRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Email
{
    const STATUS_QUEUE = "Queue";
    const STATUS_SENDING = "Sending";
    const STATUS_FAILURE = "Failed";
    const STATUS_CREATING = "Creating";
    const STATUS_SENT = "Sent";

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string", length=255)
     */
    private $filename;

    /**
     * @var string
     *
     * @ORM\Column(name="path", type="text")
     */
    private $path;

    /**
     * @var string
     *
     * @ORM\Column(name="message_from", type="string", length=255)
     */
    private $messageFrom;

    /**
     * @var string
     *
     * @ORM\Column(name="message_to", type="string", length=255, nullable=true)
     */
    private $messageTo;

    /**
     * @var string
     *
     * @ORM\Column(name="message_cc", type="string", length=255, nullable=true)
     */
    private $messageCc;

    /**
     * @var string
     *
     * @ORM\Column(name="message_bcc", type="string", length=255, nullable=true)
     */
    private $messageBcc;

    /**
     * @var string
     *
     * @ORM\Column(name="message_subject", type="string", length=255)
     */
    private $messageSubject;

    /**
     * @var string
     *
     * @ORM\Column(name="message_body", type="text")
     */
    private $messageBody;

    /**
     * @var string
     *
     * @ORM\Column(name="retry_count", type="integer", nullable=true)
     */
    private $retryCount;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255)
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_on", type="datetime")
     */
    private $createdOn;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_check", type="datetime", nullable=true)
     */
    private $lastCheck;

    /**
     * @var array
     *
     * @ORM\Column(name="attachments", type="array", nullable=true);
     */
    private $attachments;


    public function __construct() {
        $this->status = self::STATUS_QUEUE;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }



    /**
     * Set messageFrom
     *
     * @param string $messageFrom
     *
     * @return Email
     */
    public function setMessageFrom($messageFrom)
    {
        $this->messageFrom = $messageFrom;

        return $this;
    }

    /**
     * Get messageFrom
     *
     * @return string
     */
    public function getMessageFrom()
    {
        return $this->messageFrom;
    }

    /**
     * Set messageTo
     *
     * @param string $messageTo
     *
     * @return Email
     */
    public function setMessageTo($messageTo)
    {
        $this->messageTo = $messageTo;

        return $this;
    }

    /**
     * Get messageTo
     *
     * @return string
     */
    public function getMessageTo()
    {
        return $this->messageTo;
    }

    /**
     * Set messageSubject
     *
     * @param string $messageSubject
     *
     * @return Email
     */
    public function setMessageSubject($messageSubject)
    {
        $this->messageSubject = $messageSubject;

        return $this;
    }

    /**
     * Get messageSubject
     *
     * @return string
     */
    public function getMessageSubject()
    {
        return $this->messageSubject;
    }

    /**
     * Set messageBody
     *
     * @param string $messageBody
     *
     * @return Email
     */
    public function setMessageBody($messageBody)
    {
        $this->messageBody = $messageBody;

        return $this;
    }

    /**
     * Get messageBody
     *
     * @return string
     */
    public function getMessageBody()
    {
        return $this->messageBody;
    }

    /**
     * Set createdOn
     * @ORM\PrePersist
     *
     * @return Email
     */
    public function setCreatedOn()
    {
        $this->createdOn = new \DateTime();

        return $this;
    }

    /**
     * Get createdOn
     *
     * @return \DateTime
     */
    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    /**
     * Set filename
     *
     * @param string $filename
     *
     * @return Email
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return Email
     */
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set status
     *
     * @param string $status
     *
     * @return Email
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getRetryCount()
    {
        return $this->retryCount;
    }

    /**
     * @param string $retryCount
     * @return Email
     */
    public function setRetryCount($retryCount)
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    /**
     * Set lastCheck
     *
     * @param \DateTime $lastCheck
     *
     * @return Email
     */
    public function setLastCheck($lastCheck)
    {
        $this->lastCheck = $lastCheck;

        return $this;
    }

    /**
     * Get lastCheck
     *
     * @return \DateTime
     */
    public function getLastCheck()
    {
        return $this->lastCheck;
    }

    /**
     * @return string
     */
    public function getMessageCc()
    {
        return $this->messageCc;
    }

    /**
     * @param string $messageCc
     * @return Email
     */
    public function setMessageCc($messageCc)
    {
        $this->messageCc = $messageCc;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessageBcc()
    {
        return $this->messageBcc;
    }

    /**
     * @param string $messageBcc
     * @return Email
     */
    public function setMessageBcc($messageBcc)
    {
        $this->messageBcc = $messageBcc;
        return $this;
    }

    /**
     * @return array
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param array $attachments
     * @return Email
     */
    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
        return $this;
    }

}
