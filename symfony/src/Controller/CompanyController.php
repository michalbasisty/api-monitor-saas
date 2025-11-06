<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/companies')]
class CompanyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompanyRepository $companyRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'app_company_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // For now, list all companies, or filter by user
        $companies = $this->companyRepository->findAll();

        return $this->json([
            'total' => count($companies),
            'companies' => array_map(fn($c) => $this->serializeCompany($c), $companies)
        ]);
    }

    #[Route('', name: 'app_company_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['name'])) {
            return $this->json(['message' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $company = new Company();
        $company->setName($data['name']);
        $company->setOwnerId($user->getId());

        $errors = $this->validator->validate($company);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Company created successfully',
            'company' => $this->serializeCompany($company)
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_company_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $company = $this->companyRepository->find($id);

        if (!$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeCompany($company));
    }

    #[Route('/{id}', name: 'app_company_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $company = $this->companyRepository->find($id);

        if (!$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns the company
        if ($company->getOwnerId() !== $user->getId()) {
            return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $company->setName($data['name']);
        }

        $company->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($company);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Company updated successfully',
            'company' => $this->serializeCompany($company)
        ]);
    }

    #[Route('/{id}', name: 'app_company_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $company = $this->companyRepository->find($id);

        if (!$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns the company
        if ($company->getOwnerId() !== $user->getId()) {
            return $this->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($company);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Company deleted successfully'
        ]);
    }

    private function serializeCompany(Company $company): array
    {
        return [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'owner_id' => $company->getOwnerId(),
            'created_at' => $company->getCreatedAt()->format('c'),
            'updated_at' => $company->getUpdatedAt()->format('c')
        ];
    }
}
