<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

use Predis\Client;

use App\Helper\JWT;

class ProjectController extends AbstractController
{
    private Serializer $serializer;
    private mixed $user_id;
    private mixed $token;
    private bool $auth;
    private Client $redis;

    public function __construct(RequestStack $requestStack)
    {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__ .'/../../.env');

        $this->redis = new Client([
            'host' => '109.172.90.152',
            'port' => '6379',
            'username' => 'root',
            'password' => 'YanSvin2007'
        ]);
        $this->serializer = new Serializer(
            [
                new ArrayDenormalizer(),
                new DateTimeNormalizer(),
                new ObjectNormalizer(
                    null,
                    null,
                    null,
                    new ReflectionExtractor()
                )
            ],
            [new JsonEncoder()]
        );

        $jwt = new JWT();

        $request = $requestStack->getCurrentRequest();
        $this->user_id = $request->get('uid');
        $this->token = $request->get('token');

        $this->auth = $jwt->validate($this->user_id, $this->token);
    }

    #[Route('/api/project/create', name: 'create_project', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        if ($this->auth) {
            $name = $request->get('name');
            $domain = $request->get('domain');
            $owner = $this->user_id;

            if ($name and $domain) {
                $check = $manager->getRepository(Project::class)->findBy(['domain' => $domain]);
            } else {
                return new JsonResponse(['code' => 'BC1'], 400);
            }

            if (!$check) {
                $company = new Project();

                $company->setName($name);
                $company->setDomain($domain);
                $company->setOwner($owner);

                $manager->persist($company);
                $manager->flush();

                $data = $manager->getRepository(Project::class)->findBy(['owner' => $owner]);
                $data = $this->serializer->normalize($data, 'array');

                $this->redis->set('project:all:' . $owner, json_encode($data));

                return new JsonResponse(['code' => 'true'], 200);
            } else {
                return new JsonResponse(['code' => 'BC2'], 400);
            }
        } else {
            return new JsonResponse(['code' => 'BC3'], 401);
        }
    }

    #[Route('/api/project/all', name: 'read_all_projects', methods: 'POST')]
    public function readAll(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        if ($this->auth) {
            $owner = $this->user_id;

            $data = $this->redis->get('project:all:' . $owner);

            if (!$data) {
                $data = $manager->getRepository(Project::class)->findBy(['owner' => $owner]);
                $data = $this->serializer->normalize($data, 'array');

                $this->redis->set('project:all:' . $owner, json_encode($data));
                return new JsonResponse($data, 200);
            } else {
                return new JsonResponse($data, 200, json: true);
            }
        } else {
            return new JsonResponse(['code' => 'BA1'], 401);
        }
    }

    #[Route('/api/project/one', name: 'read_one_project', methods: 'POST')]
    public function readOne(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        if ($this->auth) {
            $id = $request->get('id');

            if ($id) {
                $data = $manager->getRepository(Project::class)->find($id);

                if ($data) {
                    $data = $this->serializer->normalize($data, 'array');

                    return new JsonResponse($data, 200);
                } else {
                    return new JsonResponse(['code' => 'BO1'], 400);
                }
            } else {
                return new JsonResponse(['code' => 'BO2'], 400);
            }
        } else {
            return new JsonResponse(['code' => 'BO3'], 401);
        }
    }

    #[Route('/api/project/update', name: 'update_project', methods: 'POST')]
    public function update(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        if ($this->auth) {
            $id = $request->get('id');
            $name = $request->get('name');
            $domain = $request->get('domain');

            if ($id and $name and $domain) {
                $check = $manager->getRepository(Project::class)->findBy(['domain' => $domain]);
                $check = $this->serializer->normalize($check, 'array');

                foreach ($check as $item) {
                    if ($item['id'] != $id) {
                        return new JsonResponse(['code' => 'BU1'], 400);
                    }
                }

                $data = $manager->getRepository(Project::class)->find($id);

                if ($data) {
                    $data->setName($name);
                    $data->setDomain($domain);

                    $manager->flush();

                    return new JsonResponse([], 200);
                } else {
                    return new JsonResponse(['code' => 'BU2'], 400);
                }
            } else {
                return new JsonResponse(['code' => 'BU3'], 400);
            }
        } else {
            return new JsonResponse(['code' => 'BU4'], 401);
        }
    }

    #[Route('/api/project/delete', name: 'delete_project', methods: 'POST')]
    public function delete(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        if ($this->auth) {
            $id = $request->get('id');

            if ($id) {
                $data = $manager->getRepository(Project::class)->find($id);

                if ($data) {
                    $manager->remove($data);
                    $manager->flush();

                    return new JsonResponse([], 400);
                } else {
                    return new JsonResponse(['code' => 'BD1'], 400);
                }
            } else {
                return new JsonResponse(['code' => 'BD2'], 400);
            }
        } else {
            return new JsonResponse(['code' => 'BD3'], 401);
        }
    }

    #[Route('/api/project/select_plan', name: 'select_project_plan', methods: 'POST')]
    public function selectPlan(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        if ($this->auth) {
            $id = $request->get('id');
            $plan = $request->get('plan');

            if ($id and $plan) {
                $company = $manager->getRepository(Project::class)->find($id);

                if ($company) {
                    $company->setPlan($plan);

                    $manager->flush();

                    return new JsonResponse([], 200);
                } else {
                    return new JsonResponse(['code' => "BS1"], 400);
                }
            } else {
                return new JsonResponse(['code' => "BS2"], 400);
            }
        } else {
            return new JsonResponse(['code' => "BS3"], 401);
        }
    }

    #[Route('/api/project/add_contributor', name: 'add_project_contributor', methods: 'POST')]
    public function addContributor(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        if ($this->auth) {
            $email = $request->get('email');
            $id = $request->get('id');

            if ($email) {
                $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);
                $company = $manager->getRepository(Project::class)->find($id);

                if ($user and $company) {
                    $contributors = $company->getContributors();
                    $user_id = $user->getId();

                    if ($contributors == null) {
                        $contributors = $user_id . ';';
                    } else {
                        $contributorsArray = explode(';', $contributors);

                        if (!in_array($user_id, $contributorsArray)) {
                            $contributors .= $user_id . ';';
                        } else {
                            return new JsonResponse(['code' => 'BAC1'], 400);
                        }
                    }

                    $company->setContributors($contributors);

                    $manager->flush();
                    return new JsonResponse([], 200);
                } else {
                    return new JsonResponse(['code' => 'BAC2'], 400);
                }
            } else {
                return new JsonResponse(['code' => 'BAC3'], 400);
            }
        } else {
            return new JsonResponse(['code' => 'BAC4'], 401);
        }
    }
}
