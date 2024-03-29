<?php

namespace Vorkfork\Apps\Mail\Models;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\PersistentCollection;
use Vorkfork\Apps\Mail\Repositories\MailAccountsRepository;
use Vorkfork\Database\Entity;
use Vorkfork\Database\Trait\Timestamps;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @method static MailAccountsRepository repository()
 */
#[ORM\Entity(repositoryClass: MailAccountsRepository::class)]
#[ORM\Index(columns: ['id'], name: 'id')]
#[ORM\UniqueConstraint(name: 'email_user', columns: ['email', 'user'])]
#[ORM\Table(name: '`mail_accounts`')]
#[ORM\HasLifecycleCallbacks]
class Account extends Entity
{
	use Timestamps;

	#[ORM\Id]
	#[ORM\Column(type: Types::BIGINT)]
	#[ORM\GeneratedValue]
	private int $id;

	#[ORM\Column(type: Types::STRING)]
	private string|null $user = null;

	#[ORM\Column(type: Types::STRING)]
	private string $email;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(name: 'smtp_user', type: Types::STRING)]
	private string $smtpUser;

	#[ORM\Column(name: 'smtp_password', type: Types::STRING)]
	private string $smtpPassword;

	#[ORM\Column(name: 'smtp_server', type: Types::STRING)]
	private string $smtpServer;

	#[ORM\Column(name: 'smtp_port', type: Types::INTEGER)]
	private int $smtpPort;

	#[ORM\Column(name: 'smtp_encryption', type: Types::STRING)]
	private string $smtpEncryption;

	#[ORM\Column(name: 'imap_user', type: Types::STRING)]
	private string $imapUser;

	#[ORM\Column(name: 'imap_password', type: Types::STRING)]
	private string $imapPassword;

	#[ORM\Column(name: 'imap_server', type: Types::STRING)]
	private string $imapServer;

	#[ORM\Column(name: 'imap_port', type: Types::INTEGER)]
	private int $imapPort;

	#[ORM\Column(name: 'imap_encryption', type: Types::STRING)]
	private string $imapEncryption;

	#[ORM\Column(name: 'is_default', columnDefinition: "BOOLEAN AFTER `imap_encryption`")]
	private bool $isDefault;

	#[ORM\Column(
		name: 'last_sync',
		type: Types::DATETIME_MUTABLE,
		nullable: true,
	)]
	private DateTime $lastSync;

	#[ORM\OneToMany(mappedBy: 'account', targetEntity: Mailbox::class)]
	#[ORM\JoinColumn(name: 'id', referencedColumnName: 'account_id')]
	#[ORM\OrderBy(['position' => 'ASC'])]
	private Collection $mailboxes;

	public function __construct()
	{
		parent::__construct();
		$this->mailboxes = new ArrayCollection();
	}

	protected array $fillable = [
		'user',
		'email',
		'name',
		'smtpUser',
		'smtpPassword',
		'smtpServer',
		'smtpPort',
		'smtpEncryption',
		'imapUser',
		'imapPassword',
		'imapServer',
		'imapPort',
		'imapEncryption',
		'isDefault',
		'lastSync'
	];

	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getUser(): string
	{
		return $this->user;
	}

	/**
	 * @param string $user
	 */
	public function setUser(string $user): void
	{
		$this->user = $user;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getEmail(): string
	{
		return $this->email;
	}

	/**
	 * @param string $email
	 */
	public function setEmail(string $email): void
	{
		$this->email = $email;
	}

	/**
	 * @return string
	 */
	public function getSmtpUser(): string
	{
		return $this->smtpUser;
	}

	/**
	 * @param string $smtpUser
	 */
	public function setSmtpUser(string $smtpUser): void
	{
		$this->smtpUser = $smtpUser;
	}

	/**
	 * @return string
	 */
	public function getSmtpPassword(): string
	{
		return $this->smtpPassword;
	}

	/**
	 * @param string $smtpPassword
	 */
	public function setSmtpPassword(string $smtpPassword): void
	{
		$this->smtpPassword = $smtpPassword;
	}

	/**
	 * @return string
	 */
	public function getSmtpServer(): string
	{
		return $this->smtpServer;
	}

	/**
	 * @param string $smtpServer
	 */
	public function setSmtpServer(string $smtpServer): void
	{
		$this->smtpServer = $smtpServer;
	}

	/**
	 * @return int
	 */
	public function getSmtpPort(): int
	{
		return $this->smtpPort;
	}

	/**
	 * @param int $smtpPort
	 */
	public function setSmtpPort(int $smtpPort): void
	{
		$this->smtpPort = $smtpPort;
	}

	/**
	 * @return string
	 */
	public function getSmtpEncryption(): string
	{
		return $this->smtpEncryption;
	}

	/**
	 * @param string $smtpEncryption
	 */
	public function setSmtpEncryption(string $smtpEncryption): void
	{
		$this->smtpEncryption = $smtpEncryption;
	}

	/**
	 * @return string
	 */
	public function getImapUser(): string
	{
		return $this->imapUser;
	}

	/**
	 * @param string $imapUser
	 */
	public function setImapUser(string $imapUser): void
	{
		$this->imapUser = $imapUser;
	}

	/**
	 * @return string
	 */
	public function getImapPassword(): string
	{
		return $this->imapPassword;
	}

	/**
	 * @param string $imapPassword
	 */
	public function setImapPassword(string $imapPassword): void
	{
		$this->imapPassword = $imapPassword;
	}

	/**
	 * @return string
	 */
	public function getImapServer(): string
	{
		return $this->imapServer;
	}

	/**
	 * @param string $imapServer
	 */
	public function setImapServer(string $imapServer): void
	{
		$this->imapServer = $imapServer;
	}

	/**
	 * @return int
	 */
	public function getImapPort(): int
	{
		return $this->imapPort;
	}

	/**
	 * @param int $imapPort
	 */
	public function setImapPort(int $imapPort): void
	{
		$this->imapPort = $imapPort;
	}

	/**
	 * @return string
	 */
	public function getImapEncryption(): string
	{
		return $this->imapEncryption;
	}

	/**
	 * @param string $imapEncryption
	 */
	public function setImapEncryption(string $imapEncryption): void
	{
		$this->imapEncryption = $imapEncryption;
	}

	/**
	 * @return bool
	 */
	public function getIsDefault(): bool
	{
		return $this->isDefault;
	}

	/**
	 * @param bool $isDefault
	 */
	public function setIsDefault(bool $isDefault): void
	{
		$this->isDefault = $isDefault;
	}

	/**
	 * @return DateTime
	 */
	public function getLastSync(): DateTime
	{
		return $this->lastSync;
	}

	/**
	 * @param DateTime $lastSync
	 */
	public function setLastSync(DateTime $lastSync): void
	{
		$this->lastSync = $lastSync;
	}

	/**
	 * @param \Closure|null $filter
	 * @return Collection
	 */
	public function getMailboxes(\Closure $filter = null): Collection
	{
		return $this->mailboxes->filter(function (Mailbox $element) use ($filter) {
			if (is_callable($filter)) {
				return $filter($element);
			} else {
				return $element->getParent() === null;
			}
		});
	}

	/**
	 * @param Collection $mailboxes
	 */
	public function setMailboxes(Collection $mailboxes): void
	{
		$this->mailboxes = $mailboxes;
	}

	/**
	 * @param Mailbox $mailbox
	 * @return Account
	 */
	public function addMailbox(Mailbox $mailbox)
	{

		$ind = $this->mailboxes->indexOf($mailbox);
		if ($ind === false) {
			$this->mailboxes->add($mailbox);
		} else {
			$this->mailboxes->set($ind, $mailbox);
		}
		return $this;
	}

	public function removeMailbox(Mailbox $mailbox)
	{
		$this->mailboxes->removeElement($mailbox);
		return $this;
	}

	/**
	 * @throws OptimisticLockException
	 * @throws MissingMappingDriverImplementation
	 * @throws ORMException
	 */
	public function removeUnusedMailboxes(array|ArrayCollection $existingMailboxesNames, ArrayCollection|PersistentCollection $mailboxes = null)
	{
		$mailboxes = is_null($mailboxes) ? $this->mailboxes : $mailboxes;
		$mailboxes->map(function (Mailbox $el) use ($existingMailboxesNames) {
			if ($el->getChildren()->count() > 0) {
				$this->removeUnusedMailboxes($existingMailboxesNames, $el->getChildren());
			}
			if ($existingMailboxesNames->indexOf($el->getPath()) === false) {
				$el->setParent(null);
				$el->clearChildren();
				$el->remove();
			}
		});
		$this->em()->flush();
		return $this;
	}
}
