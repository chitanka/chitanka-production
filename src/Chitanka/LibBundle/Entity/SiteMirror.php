<?php

namespace Chitanka\LibBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Chitanka\LibBundle\Entity\SiteMirrorRepository")
 * @ORM\Table(name="site_mirror")
 */
class SiteMirror extends Entity
{
	/**
	 * @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @ORM\Column(type="string", length=255, unique=true)
	 * @Assert\NotBlank
	 */
	private $url;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $last_update;

	public function getId() { return $this->id; }

	public function setUrl($url) { $this->url = $url; }
	public function getUrl() { return $this->url; }

	public function setLastUpdate($last_update) { $this->last_update = $last_update; }
	public function getLastUpdate() { return $this->last_update; }

}
