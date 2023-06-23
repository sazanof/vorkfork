<?php

namespace Vorkfork\Apps\Mail\IMAP;

use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Illuminate\Pagination\LengthAwarePaginator;
use Vorkfork\Apps\Mail\IMAP\DTO\MailboxImapDTO;
use Vorkfork\Apps\Mail\Models\Mailbox as MailboxModel;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Vorkfork\Apps\Mail\Encryption\MailPassword;
use Vorkfork\Apps\Mail\IMAP\Exceptions\ImapErrorException;
use Vorkfork\Apps\Mail\Models\Account;
use Vorkfork\Apps\Mail\Models\Recipient;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Vorkfork\Apps\Mail\Models\Message as MessageModel;

const MESSAGES_PER_PAGE = 50;

final class MailboxSynchronizer
{
	protected ?Mailbox $mailbox = null;
	protected ?Server $server = null;
	protected ?Account $account = null;
	protected static ?MailboxSynchronizer $instance = null;
	protected Folder $folder;
	protected MailboxImapDTO $mailboxDTO;
	protected ?LengthAwarePaginator $paginator = null;


	/**
	 * @param Account $account
	 * @param string $mailbox
	 * @param int $flags
	 * @param int $retries
	 * @param array $options
	 * @throws EnvironmentIsBrokenException
	 * @throws ImapErrorException
	 * @throws WrongKeyOrModifiedCiphertextException
	 */
	public function __construct(
		Account $account,
		string  $mailbox = 'INBOX'
	)
	{
		$this->server = new Server(
			host: $account->getImapServer(),
			port: $account->getImapPort(),
			mailbox: $mailbox,
			encryption: $account->getImapEncryption(),
			validateCert: true // TODO move to account creating
		);
		$this->mailbox = new Mailbox(
			server: $this->server,
			username: $account->getImapUser(),
			password: MailPassword::decrypt($account->getImapPassword()),
		);
		$this->account = $account;
		self::$instance = $this;
		return $this;
	}

	/**
	 * @param Account $account
	 * @param string $mailbox
	 * @param int $flags
	 * @param int $retries
	 * @return MailboxSynchronizer|null
	 * @throws EnvironmentIsBrokenException
	 * @throws ImapErrorException
	 * @throws WrongKeyOrModifiedCiphertextException
	 */
	public static function register(Account $account, string $mailbox = 'INBOX', int $flags = OP_HALFOPEN, int $retries = 3): ?MailboxSynchronizer
	{
		if (is_null(self::$instance)) {
			return new self(account: $account,
				mailbox: $mailbox
			);
		}
		return self::$instance;
	}

	/**
	 * @return Mailbox|null
	 */
	public function getMailbox(): ?Mailbox
	{
		return $this->mailbox;
	}

	/**
	 * @return Account|null
	 */
	public function getAccount(): ?Account
	{
		return $this->account;
	}

	/**
	 * @throws RuntimeException
	 * @throws ResponseException
	 * @throws ImapErrorException
	 * @throws FolderFetchingException
	 * @throws ImapBadRequestException
	 * @throws ConnectionFailedException
	 * @throws AuthFailedException
	 * @throws ImapServerErrorException
	 */
	public function getAllFolders(\Closure $closure = null, bool $hierarchical = false): void
	{
		$names = [];
		$imapFolders = $this->getMailbox()->getMailboxes($hierarchical);
		/** @var Folder $imapFolder */
		$i = 0;
		foreach ($imapFolders as $imapFolder) {
			if (is_callable($closure)) {
				$closure($imapFolder, $i);
			}
			$names[] = $imapFolder->full_name;
			$i++;
		}
		//$this->deleteIfMailBoxNotExists($names);
	}

	/**
	 * @param Folder $folder
	 * @param int|null $position
	 * @return void
	 * @throws ImapErrorException
	 */
	public function syncFolder(Folder $folder, int $position = 0, MailboxModel $parent = null)
	{
		$this->folder = $folder;
		$this->mailbox->ping();
		$data = [
			'name' => $folder->name,
			'path' => $folder->full_name,
			'delimiter' => $folder->delimiter,
			'total' => $folder->status['exists'],
			'unseen' => $folder->status['unseen'] ?? 0,
			'uidValidity' => $folder->status['uidvalidity'],
			'lastSync' => new \DateTime(),
		];
		$data['position'] = $position;
		/** @var MailboxModel $mbox */
		$mbox = MailboxModel::repository()
			->findOneBy([
				'account' => $this->account,
				'name' => $folder->full_name
			]);
		try {
			if (!is_null($mbox)) {
				$this->mailboxDTO = $mbox
					->update($data, function (MailboxModel $mailbox) {
						$mailbox->setAccount($this->account);
					})
					->toDto(MailboxImapDTO::class);
			} else {
				$this->mailboxDTO = MailboxModel
					::create($data, function (MailboxModel $mailbox) {
						$mailbox->setAccount($this->account);
					})
					->toDto(MailboxImapDTO::class);
			}
		} catch (\Exception $exception) {
			//dd($exception);
			// todo log
		}
	}


	/**
	 * @param Folder $folder
	 * @param int $page
	 * @param int $total
	 * @return void
	 */
	public function syncMessages(Folder $folder, int $page = 1, int $total = MESSAGES_PER_PAGE)
	{
		$this->mailbox->setFolder($folder);
		$this->paginator = $this->mailbox->getMessagesByPage($total, $page);
		if ($this->paginator->count() > 0) {
			/** @var Message $message */
			echo 'Load page ' . $page . ' from ' . $this->paginator->lastPage() . PHP_EOL;
			foreach ($this->paginator as $message) {
				try {
					$this->addMessageFromImapToDb($message);
				} catch (MissingMappingDriverImplementation $e) {
				} catch (ORMException $e) {
					dd($e);
				}
			}
		}
	}

	/**
	 * Sync all messages in folder
	 * @param Folder $folder
	 * @param int $page
	 * @param int $total
	 * @return void
	 */
	public function syncAllMessagesInFolder(Folder $folder, int $page = 1, int $total = MESSAGES_PER_PAGE)
	{
		/** @var LengthAwarePaginator $paginator */
		$this->paginator = $this->mailbox->getMessagesByPage($total, $page);
		do {
			$this->syncMessages($folder, $page);
			$page++;
		} while ($this->paginator->lastPage() >= $page);
	}

	/**
	 * @param Folder $folder
	 * @return MailboxImapDTO|null
	 * @throws ImapErrorException
	 */
	public function sync(Folder $folder): ?MailboxImapDTO
	{
		$this->syncFolder($folder);
		$this->syncAllMessagesInFolder($folder);
		return null;

	}


	/**
	 * @throws MissingMappingDriverImplementation
	 * @throws ORMException
	 */
	public function addMessageFromImapToDb(Message $message): void
	{
		$flags = $message->flags;

		$to = $message->getTo();
		$from = $message->getFrom();
		$cc = $message->getCc();
		$bcc = $message->getBcc();
		$recipientsCompact = compact('to', 'from', 'cc', 'bcc');
		$flagged = MessageFlags::isFlagged($flags);
		$important = MessageFlags::isImportant($flags);
		$answered = MessageFlags::isAnswered($flags);
		$deleted = MessageFlags::isDeleted($flags);
		$draft = MessageFlags::isDraft($flags);
		$spam = MessageFlags::isSpam($flags);
		$notSpam = MessageFlags::isNotSpam($flags);
		$recent = MessageFlags::isRecent($flags);
		$seen = MessageFlags::isSeen($flags);

		$messageId = $message->getMessageId()->first();
		$subject = $message->getSubject()->first();
		$bodyHtml = $message->getHTMLBody();
		//$preview = '';  todo = check for better
		$inReplyTo = $message->getInReplyTo()->first();
		$chain = $message->getReferences();
		$sentAt = $message->date;
		$attachments = $message->hasAttachments();

		$messageExisting = MessageModel::repository()->findOneByMessageId($messageId);

		if (is_null($messageExisting)) {
			$dbMessage = new MessageModel();
			$dbMessage->setMailboxId($this->mailboxDTO->id);
			$dbMessage->setMessageId($messageId);
			$dbMessage->setSubject($subject);
			$dbMessage->setBody($bodyHtml);
			$dbMessage->setInReplyTo($inReplyTo);
			$dbMessage->setChain($chain);
			//$dbMessage->setPreview($preview);
			$dbMessage->setSentAt($sentAt);
			$dbMessage->setAnswered($answered);
			$dbMessage->setAttachments($attachments);
			$dbMessage->setImportant($important);
			$dbMessage->setRecent($recent);
			$dbMessage->setSeen($seen);
			$dbMessage->setDraft($draft);
			$dbMessage->setFlagged($flagged);
			$dbMessage->setSpam($spam);
			$dbMessage->setNotSpam($notSpam);
			$dbMessage->setDeleted($deleted);
			$dbMessage->setLocalMessage(false);

			/** @var Attribute $item */
			foreach ($recipientsCompact as $type => $item) {
				/** @var Address $address */
				foreach ($item->all() as $address) {
					$recipient = new Recipient();
					$recipient->setName($address->personal);
					$recipient->setAddress($address->mail);
					$recipient->setTypeByString($type);
					$dbMessage->addRecipient($recipient);
					$dbMessage->em()->persist($recipient);
				}
			}
			try {
				$dbMessage->em()->persist($dbMessage);
				$dbMessage->em()->flush();
			} catch (SyntaxErrorException $e) {
				dump($e->getQuery()->getSQL());
			} catch (ORMException $e) {
				dump($e->getMessage());
			} catch (\Exception $exception) {
				dump($subject);
				//dump($exception->getFile(), $exception->getLine(), $exception->getMessage());
			}
		} else {
			$messageExisting->setBody($bodyHtml);
			//$dbMessage->setPreview($preview);
			$messageExisting->setMailboxId($this->mailboxDTO->id);
			$messageExisting->setMessageId($messageId);
			$messageExisting->setSubject($subject);
			$messageExisting->setInReplyTo($inReplyTo);
			$messageExisting->setChain($chain);
			$messageExisting->setAnswered($answered);
			$messageExisting->setImportant($important);
			$messageExisting->setRecent($recent);
			$messageExisting->setSeen($seen);
			$messageExisting->setDraft($draft);
			$messageExisting->setFlagged($flagged);
			$messageExisting->setSpam($spam);
			$messageExisting->setNotSpam($notSpam);
			$messageExisting->setDeleted($deleted);
			$messageExisting->setAttachments($attachments);

			try {
				$messageExisting->em()->persist($messageExisting);
				$messageExisting->em()->flush();
			} catch (\Exception $e) {
				dump($e->getMessage());
			}
		}
	}

	public function getDatabaseAccountMailboxes(): array
	{
		return MailboxModel::repository()->findBy(['accountId' => $this->account->getId()]);
	}

	public function deleteIfMailBoxNotExists(array $names)
	{
		// переписать на орм
		$this->account->removeUnusedMailboxes($names);
		$this->account->save();

	}
}