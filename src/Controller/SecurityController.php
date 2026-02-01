<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Controller;

use Happycode\TenZeroAuth\Event\PasswordResetRequestedEvent;
use Happycode\TenZeroAuth\Service\ConfigService;
use Happycode\TenZeroAuth\Service\TenZeroSecurityService;
use Happycode\TenZeroAuth\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public const PWD_FIELD = 'password';
    public const CONFIRM_PWD_FIELD = 'confirm-password';

    public function __construct(
        private readonly UserService $userService,
        private readonly ConfigService $config,
        private readonly TenZeroSecurityService $security,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    // Route configured via TenZeroAuthRouteLoader.
    public function login(Request $request): Response
    {
        $userField = $this->config->getUserField();
        $authError = $this->authenticationUtils->getLastAuthenticationError();
        $errors = $authError ? ['Invalid credentials.'] : null;

        return $this->render('@TenZeroAuth/security/login.html.twig', [
            'errors' => $errors,
            'userField' => $userField,
            'last_username' => $this->authenticationUtils->getLastUsername(),
        ]);
    }

    // Route configured via TenZeroAuthRouteLoader.
    public function register(Request $request): Response
    {
        if (!$this->config->isRegisterEnabled()) {
            throw $this->createNotFoundException();
        }
        $usernameField = $this->config->getUserField();
        $userFields = $this->userService->getUserFields();
        $fieldErrors = [];
        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');
            $csrfToken = new CsrfToken('register', (string) $submittedToken);
            if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
                $fieldErrors['_csrf_token'] = 'Invalid form submission.';
            }

            if (empty($fieldErrors) && $request->request->has($usernameField) && $request->request->has(self::PWD_FIELD) && $request->request->has(self::CONFIRM_PWD_FIELD)) {
                $password = $request->request->get(self::PWD_FIELD);
                $confirmPassword = $request->request->get(self::CONFIRM_PWD_FIELD);
                if ($password === $confirmPassword) {
                    $createdUser = $this->security->createUser(
                        $request->request->get($usernameField),
                        $request->request->get(self::PWD_FIELD),
                        $request->request->all()
                    );
                    if ($createdUser) {
                        return $this->redirectToRoute('tenzero_auth_login');
                    }
                } else {
                    $fieldErrors[self::PWD_FIELD] = 'Passwords do not match';
                    $fieldErrors[self::CONFIRM_PWD_FIELD] = 'Passwords do not match';
                }
            }
        }

        $errors = $this->security->lastErrors;
        $viewData = [
            'errors' => (count($errors) > 0) ? $errors : null,
            'fieldErrors' => (count($fieldErrors) > 0) ? $fieldErrors : null,
            'userField' => $usernameField,
            'otherFields' => $userFields,
        ];

        return $this->render('@TenZeroAuth/security/register.html.twig', $viewData);
    }

    // Route configured via TenZeroAuthRouteLoader.
    public function forgotPassword(Request $request): Response
    {
        $userField = $this->config->getUserField();
        $errors = [];

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');
            $csrfToken = new CsrfToken('forgot_password', (string) $submittedToken);
            if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
                $errors[] = 'Invalid form submission.';
            } else {
                $identifier = trim((string) $request->request->get($userField, ''));
                if ('' !== $identifier) {
                    $user = $this->userService->fetchUser($identifier);
                    if ($user) {
                        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                        $tokenHash = hash('sha256', $token);
                        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', $this->config->getResetPasswordLinkTtl()));
                        $this->userService->setResetPasswordToken($user, $tokenHash, $expiresAt);
                        $resetUrl = $this->generateUrl(
                            'tenzero_auth_reset_password',
                            ['token' => $token],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        );
                        $event = new PasswordResetRequestedEvent($user, $token, $expiresAt, $resetUrl);
                        $this->eventDispatcher->dispatch($event);
                    }
                }
                $this->addFlash('success', 'If an account exists, we sent a reset link.');

                return $this->redirectToRoute('tenzero_auth_forgot_password');
            }
        }

        return $this->render('@TenZeroAuth/security/forgot_password.html.twig', [
            'errors' => $errors,
            'userField' => $userField,
        ]);
    }

    // Route configured via TenZeroAuthRouteLoader.
    public function changePassword(Request $request): Response
    {
        $errors = [];
        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');
            $csrfToken = new CsrfToken('change_password', (string) $submittedToken);
            if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
                $errors[] = 'Invalid form submission.';
            }

            $oldPassword = (string) $request->request->get('old_password', '');
            $newPassword = (string) $request->request->get('new_password', '');
            $confirmNewPassword = (string) $request->request->get('confirm_new_password', '');
            if ($newPassword !== $confirmNewPassword) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                try {
                    $this->security->changeMyPassword($oldPassword, $newPassword);
                    $this->tokenStorage->setToken(null);
                    if ($request->hasSession()) {
                        $request->getSession()->invalidate();
                    }
                    $this->addFlash('success', 'Password updated. Please log in again.');

                    return $this->redirectToRoute('tenzero_auth_login');
                } catch (\RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        return $this->render('@TenZeroAuth/security/change_password.html.twig', [
            'errors' => $errors,
            'formAction' => $this->generateUrl('tenzero_auth_change_password'),
            'showOldPassword' => true,
            'csrfId' => 'change_password',
            'title' => 'Change Password',
            'tokenInvalid' => false,
        ]);
    }

    // Route configured via TenZeroAuthRouteLoader.
    public function resetPassword(Request $request, string $token): Response
    {
        $errors = [];
        $user = $this->userService->findUserByResetToken($token);
        if (!$user) {
            $errors[] = 'This reset link is invalid or has expired.';
        } elseif ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');
            $csrfToken = new CsrfToken('reset_password', (string) $submittedToken);
            if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
                $errors[] = 'Invalid form submission.';
            }

            $newPassword = (string) $request->request->get('new_password', '');
            $confirmNewPassword = (string) $request->request->get('confirm_new_password', '');
            if ($newPassword !== $confirmNewPassword) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                $this->userService->changePassword($user, $newPassword);
                $this->userService->clearResetPasswordToken($user);
                $this->addFlash('success', 'Your password has been reset. Please log in.');

                return $this->redirectToRoute('tenzero_auth_login');
            }
        }

        return $this->render('@TenZeroAuth/security/change_password.html.twig', [
            'errors' => $errors,
            'formAction' => $this->generateUrl('tenzero_auth_reset_password', ['token' => $token]),
            'showOldPassword' => false,
            'csrfId' => 'reset_password',
            'title' => 'Reset Password',
            'tokenInvalid' => !$user,
        ]);
    }

    // Route configured via TenZeroAuthRouteLoader.
    public function apiToken(): Response
    {
        return new JsonResponse(
            ['message' => 'Authentication failed.'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    // Route configured via TenZeroAuthRouteLoader.
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the firewall logout handler.');
    }
}
