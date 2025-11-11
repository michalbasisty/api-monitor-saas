<?php

namespace App\Controller;

use App\Dto\Auth\AuthResponse;
use App\Dto\Auth\LoginRequest;
use App\Dto\Auth\RegisterRequest;
use App\Exception\ApiException;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private AuthService $authService,
        private JWTTokenManagerInterface $jwtManager,
        private ValidatorInterface $validator,
        private EmailVerificationService $emailVerificationService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", minLength=8, example="password123"),
     *             @OA\Property(property="company_id", type="string", format="uuid"),
     *             @OA\Property(property="subscription_tier", type="string", enum={"free", "pro", "enterprise"}, default="free")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="user_id", type="string", format="uuid"),
     *             @OA\Property(property="verification_token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=409, description="User already exists")
     * )
     */
    #[Route('/register', name: 'app_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                throw new ApiException('Invalid JSON data', Response::HTTP_BAD_REQUEST);
            }

            $registerRequest = RegisterRequest::fromArray($data);

            // Validate the request
            $errors = $this->validator->validate($registerRequest);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                throw new ApiException(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            $user = $this->authService->register($data);

            return $this->json([
                'message' => 'User registered successfully. Please check your email for verification.',
                'user_id' => $user->getId(),
                'verification_token' => $user->getVerificationToken() // Remove in production
            ], Response::HTTP_CREATED);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('Registration failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/auth/verify-email/{token}",
     *     summary="Verify user email",
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Email verification token"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="is_verified", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid token"),
     *     @OA\Response(response=410, description="Token expired")
     * )
     */
    #[Route('/verify-email/{token}', name: 'app_auth_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token): JsonResponse
    {
        try {
            $user = $this->authService->verifyEmail($token);

            return $this->json([
                'message' => 'Email verified successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'is_verified' => $user->isVerified(),
                ]
            ]);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('Email verification failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Authenticate user and get JWT token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="subscription_tier", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=403, description="Account not verified")
     * )
     */
    #[Route('/login', name: 'app_auth_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
    try {
    $data = json_decode($request->getContent(), true);

    if (!$data) {
    return new JsonResponse(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
    }

    $loginRequest = LoginRequest::fromArray($data);

    // Validate the request
    $errors = $this->validator->validate($loginRequest);
    if (count($errors) > 0) {
    return new JsonResponse(['error' => 'Invalid login credentials'], Response::HTTP_BAD_REQUEST);
    }

    $user = $this->authService->login($loginRequest->username, $loginRequest->password);
    $token = $this->jwtManager->create($user);

    return $this->json([
    'token' => $token,
    'user' => [
    'id' => $user->getId(),
    'email' => $user->getEmail(),
    'roles' => $user->getRoles(),
    'subscription_tier' => $user->getSubscriptionTier(),
    'is_verified' => $user->isVerified(),
    'company_id' => $user->getCompanyId(),
    'created_at' => $user->getCreatedAt()->format('c'),
    'last_login_at' => $user->getLastLoginAt()?->format('c'),
    ]
    ]);

    } catch (ApiException $e) {
             throw $e;
    } catch (\Exception $e) {
    error_log('Login error: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
        throw new ApiException('Login failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Get current user information",
     *     security={{"BearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user data",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="subscription_tier", type="string"),
     *                 @OA\Property(property="company_id", type="string", format="uuid"),
     *                 @OA\Property(property="is_verified", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Not authenticated")
     * )
     */
    #[Route('/me', name: 'app_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                throw new ApiException('Not authenticated', Response::HTTP_UNAUTHORIZED);
            }

            return $this->json([
                'user' => \App\Dto\Auth\UserResponse::fromUser($user)
            ]);

        } catch (ApiException $e) {
            throw $e;
        }
    }

    #[Route('/forgot-password', name: 'app_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['email'])) {
                throw new ApiException('Email is required', Response::HTTP_BAD_REQUEST);
            }

            $this->authService->forgotPassword($data['email']);

            return $this->json([
                'message' => 'If the email exists, a reset link has been sent'
            ]);

        } catch (ApiException $e) {
            throw $e;
        }
    }

    #[Route('/reset-password', name: 'app_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['token']) || !isset($data['password'])) {
                throw new ApiException('Token and password are required', Response::HTTP_BAD_REQUEST);
            }

            $user = $this->authService->resetPassword($data['token'], $data['password']);

            return $this->json([
                'message' => 'Password has been reset successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ]
            ]);

        } catch (ApiException $e) {
        throw $e;
        } catch (\Exception $e) {
        throw new ApiException('Password reset failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        }

    #[Route('/profile', name: 'app_auth_profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                throw new ApiException('Not authenticated', Response::HTTP_UNAUTHORIZED);
            }

            return $this->json([
                'user' => $this->authService->getUserProfile($user)
            ]);

        } catch (ApiException $e) {
            throw $e;
        }
    }

    #[Route('/profile', name: 'app_auth_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
    try {
    $user = $this->getUser();

    if (!$user) {
    throw new ApiException('Not authenticated', Response::HTTP_UNAUTHORIZED);
    }

    $data = json_decode($request->getContent(), true);

    if (!$data) {
    throw new ApiException('Invalid JSON data', Response::HTTP_BAD_REQUEST);
    }

    $updatedUser = $this->authService->updateProfile($user, $data);

    return $this->json([
        'message' => 'Profile updated successfully',
                'user' => $this->authService->getUserProfile($updatedUser)
    ]);

    } catch (ApiException $e) {
    throw $e;
    } catch (\Exception $e) {
    throw new ApiException('Profile update failed', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    }

    #[Route('/change-password', name: 'app_auth_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
    try {
    $user = $this->getUser();

    if (!$user) {
    throw new ApiException('Not authenticated', Response::HTTP_UNAUTHORIZED);
    }

    $data = json_decode($request->getContent(), true);

    if (!$data || !isset($data['current_password']) || !isset($data['new_password'])) {
    throw new ApiException('Current password and new password are required', Response::HTTP_BAD_REQUEST);
    }

    $this->authService->changePassword($user, $data['current_password'], $data['new_password']);

    return $this->json([
        'message' => 'Password changed successfully'
            ]);

    } catch (ApiException $e) {
    throw $e;
    } catch (\Exception $e) {
    throw new ApiException('Password change failed', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    }

    #[Route('/resend-verification', name: 'app_auth_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['email'])) {
                throw new ApiException('Email is required', Response::HTTP_BAD_REQUEST);
            }

            $this->authService->resendVerificationEmail($data['email']);

            return $this->json([
                'message' => 'If the email exists, a verification email has been sent'
            ]);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('Failed to resend verification email', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete-account', name: 'app_auth_delete_account', methods: ['DELETE'])]
    public function deleteAccount(): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                throw new ApiException('Not authenticated', Response::HTTP_UNAUTHORIZED);
            }

            $this->authService->deleteAccount($user);

            return $this->json([
                'message' => 'Account has been deactivated'
            ]);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('Account deletion failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
