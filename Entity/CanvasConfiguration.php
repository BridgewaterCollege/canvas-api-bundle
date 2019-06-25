<?php
namespace BridgewaterCollege\Bundle\CanvasApiBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;


/**
 * @ORM\Entity()
 * @ORM\Table(name="canvas_api_configuration")
 */
class CanvasConfiguration
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=191)
     */
    public $canvasApiEnv;

    /**
     * @ORM\Column(type="string")
     */
    public $canvasApiToken;

    /**
     * @ORM\Column(type="string")
     */
    public $canvasApiUrl;

    /**
     * @ORM\Column(type="datetime")
     */
    public $lastModifiedDatetime;

    public function setLastModifiedDateTime($time) {
        $dateObj = new \DateTime($time, new \DateTimeZone('America/New_York'));
        $this->lastModifiedDatetime = $dateObj;
    }
}