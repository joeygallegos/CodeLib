<?php

namespace App\Models;

use Mailgun\Mailgun;
use App\Models\Config;

class EmailEngine
{
	private $publicMailgun;
	private $privateMailgun;
	private $domain;
	private $logger;

	/**
	 * This is a Model that allows you to quickly send emails to the Mailgun API. 
	 * @param array $setup
	 */
	public function __construct(array $setup = [])
	{
		$this->publicMailgun = Mailgun::create($setup['public_key']);
		$this->privateMailgun = Mailgun::create($setup['private_key']);
		$this->domain = $setup['domain'];
		$this->fromEmail = $setup['from_email'];
		$this->logger = $setup['logger'];
	}

	/**
	 * Returns a private instance of the mailgun endpoint API
	 * @return Mailgun Object
	 */
	public function getPrivateMailgunInstance()
	{
		return $this->privateMailgun;
	}

	/**
	 * Returns a public instance of the mailgun endpoint API
	 * @return Mailgun Object
	 */
	public function getPublicMailgunInstance()
	{
		return $this->publicMailgun;
	}

	/**
	 * Get domain name
	 * @return String domain for mailgun
	 */
	public function getDomain()
	{
		return $this->domain;
	}

	/**
	 * Get from email
	 * @return String from email for mailgun
	 */
	public function getFromEmail()
	{
		return $this->fromEmail;
	}

	/**
	 * Get unsubscribes
	 * @return Array email unsubscribes
	 */
	public function getUnsubscribes()
	{
		return $this->privateMailgun->get($this->domain . '/unsubscribes');
	}

	/**
	 * Get bounces
	 * @return Array email bounces
	 */
	public function getBounces()
	{
		return $this->privateMailgun->get($this->domain . '/bounces');
	}

	/**
	 * Get complaints
	 * @return Array email complaints
	 */
	public function getComplaints()
	{
		return $this->privateMailgun->get($this->domain . '/complaints');
	}

	/**
	 * Get events
	 * @link https://documentation.mailgun.com/en/latest/api-events.html#examples
	 * @return Array events
	 */
	public function getEvents($query = [])
	{
		if (empty($query)) return null;
		return $this->privateMailgun->get($this->domain . '/events', $query);
	}

	/**
	 * Validate that an email address is a valid email.
	 * @param  Mailgun  $mailgun Mailgun instance
	 * @param  string  $email Email address to be checked
	 * @return boolean If email is valid
	 */
	public function isValidEmail($email = '')
	{
		$this->logger->info("Checking email address: {$email}");

		// basic sanity check
		$emailIsValid = !filter_var($email, FILTER_VALIDATE_EMAIL) === false;
		if ($emailIsValid) {
			$this->logger->info('Email matches regex check.. Testing with Mailgun..');
		}

		// if no external checking
		if (!getenv('MAILGUN_USE_EXTERNAL_VALIDATION') && $emailIsValid) {
			$this->logger->info('External validation disabled, email passed');
			return $emailIsValid;
		}

		// try the external validation
		try {
			$result = $this->publicMailgun->emailValidation->validate($email, false);
			$this->logger->info("API response: " . var_export($result, true));

			$valid = $result->http_response_body->is_valid > 0;
			$this->logger->info("API.http_response_body.is_valid=" . ($valid ? "true" : "false") . "");

			if ($emailIsValid && $valid) {
				$this->logger->info('Email pre-check and API check both passed');
			}

			if ($emailIsValid) {
				if (!$valid) {
					$this->logger->info('Email pre-check passed but API check did not pass');
				}
			}

			return $valid;
		} catch (\Exception $e) {
			$this->logger->error("Failure while checking email: {$email}");
			$this->logger->error("{$e->getMessage()}");
			$this->logger->error('Using normal means to check email..');

			return $emailIsValid;
		}
	}

	/**
	 * Validate that an email address is a valid email.
	 * @param  Mailgun  $mailgun Mailgun instance
	 * @param  string  $email Email address to be checked
	 * @return boolean If email is valid
	 */
	public function isDisposableEmail($email = '', $container)
	{
		if (!Config::get('external_validation', false)) {
			return false;
		}

		$this->logger->info("Checking if disposable email: {$email}");
		try {
			$result = $this->publicMailgun->get("address/validate", ['address' => $email]);
			$this->logger->info("API response: " . var_export($result, true));

			$valid = $result->http_response_body->is_disposable_address > 0;
			$this->logger->info("API.http_response_body.is_disposable_address=" . ($valid ? "true" : "false") . "");
			return $valid;
		} catch (\Exception $e) {
			$this->logger->error("Failure while checking email: {$email}");
			$this->logger->error("{$e->getMessage()}");
			return false;
		}
	}

	/**
	 * Create and send an email to a user
	 * @param  array $parameters settings for email
	 */
	public function createEmail(array $parameters)
	{
		$this->logger->info(sprintf("Sending email: %s", json_encode($parameters)));
		$to = isset($parameters['to']) ? $parameters['to'] : null;
		$bcc = isset($parameters['bcc']) ? $parameters['bcc'] : null;
		$from = isset($parameters['from']) ? $parameters['from'] : null;
		$replyTo = isset($parameters['reply-to']) ? $parameters['reply-to'] : null;
		$subject = isset($parameters['subject']) ? $parameters['subject'] : null;
		$text = isset($parameters['text']) ? $parameters['text'] : null;
		$html = isset($parameters['html']) ? $parameters['html'] : null;

		if (!isset($parameters['from'])) {
			$parameters['from'] = getenv('ADMINTOOLS_FROM');
		}

		$response = false;
		try {
			$response = $this->privateMailgun->messages()->send($this->getDomain(), [
				'from' => $from,
				'to' => $to,
				'bcc' => $bcc,
				'h:reply-to' => $replyTo,
				'subject' => $subject,
				'text' => $text,
				'html' => $html
			]);
		} catch (\RuntimeException $e) {
			$this->logger->error('RuntimeException thrown');
			$this->logger->error("{$e->getMessage()}");
			$this->logger->error("" . json_encode($parameters));
		}
		return $response;
	}
}
