<?php
namespace BridgewaterCollege\Bundle\CanvasApiBundle\Utils;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class Type: Process Handler
 */
class ProcessHandler
{
    protected $validator_service;
    protected $em;
    protected $emailer;
    protected $logger;
    protected $serializer;
    protected $container;

    public $errorsArray = array(); // error's array set on validateEntity
    public $pageCanSubmit = false; // determines if the current screen/page can submit (flag)

    public function __construct(ValidatorInterface $validator_service, EntityManagerInterface $em, LoggerInterface $logger, SerializerInterface $serializer, ContainerInterface $container)
    {
        $this->validator_service = $validator_service;
        $this->em = $em;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->container = $container;
    }

    public function computeEntityChangedSet($entity) {
        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        return $uow->getEntityChangeSet($entity);
    }

    public function validateDateIsFuture($dateSelected) {
        // Check the date's type that was passed into this function... integer, string etc...
        $currentDate = strtotime(date('Y-m-d'));
        if (is_string($dateSelected)) {
            $dateSelected = strtotime($dateSelected);
        }

        if ($currentDate > $dateSelected)
            return false;
        else
            return true;
    }

    public function validateEntity($entity, $validationGroups) {
        $errors = $this->validator_service->validate($entity, null, $validationGroups);
        $this->logger->warning(print_r($errors, true));
        $this->errorsArray = array();
        if (count($errors) > 0) {
            $errorsArray = array();
            foreach ($errors as $error)
                $this->errorsArray[$error->getPropertyPath()] = $error->getMessage();
        }
        $this->pageCanSubmit = (count($errors) > 0 ? false : true);
    }

    public function paginateQuery($query, $page = 1, $limit = 10) {
        /** Paginates a doctrine query to only return the requested page worth of results (defaults: page 1, limit 10 objects) */
        $paginator = new Paginator($query);
        $paginator->getQuery()
            ->setFirstResult($limit * ($page - 1)) // Offset
            ->setMaxResults($limit); // Limit
        return $paginator;
    }
}