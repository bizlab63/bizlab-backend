<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;

use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\Tag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        $this->redis = new Client();
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

    #[Route('/api/company/create', name: 'create_company', methods: ['POST'])]
    #[Response(
        response: 200,
        description: 'Returns an array with code field. Everything\'s fine.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 400,
        description: 'Returns an array with code field. Something is wrong with the data.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 401,
        description: 'Returns an array with code field. The user has not been authenticated.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Parameter(
        name: 'name',
        description: 'The field contains company name',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'domain',
        description: 'The field contains company domain',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'Company')]
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

                return new JsonResponse([], 200);
            } else {
                return new JsonResponse(['code' => 'BC2'], 400);
            }
        } else {
            return new JsonResponse(['code' => 'BC3'], 401);
        }
    }

    #[Route('/api/company/all', name: 'read_all_companies', methods: 'POST')]
    #[Response(
        response: 200,
        description: 'Returns an array with code field. Everything\'s fine.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string'),
                    new Property(property: 'data', type: 'array', items: new Items(ref: new Model(type: Project::class)))
                ]
            )
        )
    )]
    #[Response(
        response: 400,
        description: 'Returns an array with code field. Something is wrong with the data.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 401,
        description: 'Returns an array with code field. The user has not been authenticated.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'Company')]
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

    #[Route('/api/company/one', name: 'read_one_company', methods: 'POST')]
    #[Response(
        response: 200,
        description: 'Returns an array with code field. Everything\'s fine.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string'),
                    new Property(property: 'data', ref: new Model(type: Project::class))
                ]
            )
        )
    )]
    #[Response(
        response: 400,
        description: 'Returns an array with code field. Something is wrong with the data.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 401,
        description: 'Returns an array with code field. The user has not been authenticated.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Parameter(
        name: 'id',
        description: 'The field contains company id',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'Company')]
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

    #[Route('/api/company/update', name: 'update_company', methods: 'POST')]
    #[Response(
        response: 200,
        description: 'Returns an array with code field. Everything\'s fine.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 400,
        description: 'Returns an array with code field. Something is wrong with the data.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 401,
        description: 'Returns an array with code field. The user has not been authenticated.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Parameter(
        name: 'id',
        description: 'The field contains company id',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'name',
        description: 'The field contains company name',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'domain',
        description: 'The field contains company domain',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'Company')]
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

    #[Route('/api/company/delete', name: 'delete_company', methods: 'POST')]
    #[Response(
        response: 200,
        description: 'Returns an array with code field. Everything\'s fine.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 400,
        description: 'Returns an array with code field. Something is wrong with the data.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 401,
        description: 'Returns an array with code field. The user has not been authenticated.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Parameter(
        name: 'id',
        description: 'The field contains company id',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'Company')]
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

    #[Route('/api/company/select_plan', name: 'select_company_plan', methods: 'POST')]
    #[Response(
        response: 200,
        description: 'Returns an array with code field. Everything\'s fine.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 400,
        description: 'Returns an array with code field. Something is wrong with the data.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 401,
        description: 'Returns an array with code field. The user has not been authenticated.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Parameter(
        name: 'id',
        description: 'The field contains company id',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'plan',
        description: 'The field contains plan id',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'Company')]
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

    #[Route('/api/company/add_contributor', name: 'add_company_contributor', methods: 'POST')]
    #[Response(
        response: 200,
        description: 'Returns an array with code field. Everything\'s fine.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 400,
        description: 'Returns an array with code field. Something is wrong with the data.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Response(
        response: 401,
        description: 'Returns an array with code field. The user has not been authenticated.',
        content: new JsonContent(
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'code', type: 'string')
                ]
            )
        )
    )]
    #[Parameter(
        name: 'id',
        description: 'The field contains company id',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'email',
        description: 'The field contains user email',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'Company')]
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
