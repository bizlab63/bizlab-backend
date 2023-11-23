<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $domain = null;

    #[ORM\Column]
    private ?int $owner = null;

    #[ORM\Column(nullable: true)]
    private ?int $plan = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contributors = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getOwner(): ?int
    {
        return $this->owner;
    }

    public function setOwner(int $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getPlan(): ?int
    {
        return $this->plan;
    }

    public function setPlan(?int $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getContributors(): ?string
    {
        return $this->contributors;
    }

    public function setContributors(?string $contributors): static
    {
        $this->contributors = $contributors;

        return $this;
    }
}
