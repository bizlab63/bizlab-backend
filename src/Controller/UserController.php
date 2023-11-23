<?php

namespace App\Controller;

use App\Entity\User;

use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\Tag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\JWT;

class UserController extends AbstractController
{
    private JWT $jwt;

    public function __construct()
    {
        $this->jwt = new JWT();
    }

    private function random_code(int $length): string
    {
        $letters = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $letters[rand(0, 9)];
        }

        return $code;
    }

    private function register(EntityManagerInterface $manager, string $email): void
    {
        $user = new User();
        $user->setEmail($email);

        $manager->persist($user);
        $manager->flush();
    }

    #[Route('/api/user/login', name: 'login_user', methods: 'POST')]
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
        response: 403,
        description: 'Returns an array with code field. The user has already been authenticated.',
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
    #[Tag(name: 'User')]
    public function login(Request $request, EntityManagerInterface $manager, MailerInterface $mailer): JsonResponse
    {
        $email = $request->get('email');

        if ($email) {
            $check = $manager->getRepository(User::class)->findBy(['email' => $email]);

            if (!$check) {
                $this->register($manager, $email);
            }

            $code = $this->random_code(4);

            $mail = (new Email())
                ->from('info@bizlab.space')
                ->to($email)
                ->subject('БизЛаб - Код подтверждения')
                ->html('<h1>' . $code . '</h1>');

            $mailer->send($mail);

            $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);
            $user->setCode($code);
            $manager->flush();

            return new JsonResponse(['code' => 'true'], 200);
        } else {
            return new JsonResponse(['code' => 'AI1'], 400);
        }
    }

    #[Route('/api/user/auth', name: 'auth_user', methods: 'POST')]
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
        response: 403,
        description: 'Returns an array with code field. The user has already been authenticated.',
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
        name: 'email',
        description: 'The field contains user email',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'code',
        description: 'The field contains authentication code from email',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string')
    )]
    #[Parameter(
        name: 'user_id',
        description: 'The field contains user id',
        in: 'cookie',
        schema: new Schema(type: 'string')
    )]
    #[Tag(name: 'User')]
    public function auth(Request $request, EntityManagerInterface $manager): JsonResponse
    {
        $email = $request->get('email');
        $code = $request->get('code');

        $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user->getCode() == $code) {
            $uid = $user->getId();

            $token = $this->jwt->generate($uid);

            return new JsonResponse(['code' => 'true', 'token' => $token, 'uid' => $uid], 200);
        } else {
            return new JsonResponse(['code' => 'AA1'], 400);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @deprecated
     *
     */
    #[Route('/api/user/logout', name: 'logout_user', methods: 'POST')]
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
    #[Tag(name: 'User')]
    public function logout(Request $request): JsonResponse
    {
        $session = $request->getSession();

        if ($session->get('user_id') != null) {
            $session->set('user_id', null);

            return new JsonResponse(['code' => 'true'], 200);
        } else {
            return new JsonResponse(['code' => 'AO1'], 401);
        }
    }
}
