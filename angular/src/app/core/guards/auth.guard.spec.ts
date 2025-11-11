import { TestBed } from '@angular/core/testing';
import { Router, UrlTree } from '@angular/router';
import { authGuard } from './auth.guard';
import { AuthService } from '../services/auth.service';

describe('authGuard', () => {
  let authService: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(() => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', [
      'isAuthenticated'
    ]);
    const routerSpy = jasmine.createSpyObj('Router', ['createUrlTree']);

    TestBed.configureTestingModule({
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: Router, useValue: routerSpy }
      ]
    });

    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;
  });

  // ============================================
  // Authentication Tests
  // ============================================

  it('should allow access when user is authenticated', () => {
    authService.isAuthenticated.and.returnValue(true);

    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(result).toBe(true);
  });

  it('should deny access when user is not authenticated', () => {
    authService.isAuthenticated.and.returnValue(false);
    router.createUrlTree.and.returnValue({} as UrlTree);

    TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(router.createUrlTree).toHaveBeenCalledWith(['/login']);
  });

  // ============================================
  // Route Navigation Tests
  // ============================================

  it('should redirect to login when not authenticated', () => {
    authService.isAuthenticated.and.returnValue(false);
    const urlTree = { toString: () => '/login' } as UrlTree;
    router.createUrlTree.and.returnValue(urlTree);

    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(result).toEqual(urlTree);
  });

  it('should call createUrlTree with login route', () => {
    authService.isAuthenticated.and.returnValue(false);
    router.createUrlTree.and.returnValue({} as UrlTree);

    TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(router.createUrlTree).toHaveBeenCalledWith(['/login']);
  });

  // ============================================
  // Multiple Check Tests
  // ============================================

  it('should check authentication status on each activation', () => {
    authService.isAuthenticated.and.returnValue(true);

    TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(authService.isAuthenticated).toHaveBeenCalled();

    TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(authService.isAuthenticated).toHaveBeenCalledTimes(2);
  });

  it('should handle state changes between checks', () => {
    // First check: authenticated
    authService.isAuthenticated.and.returnValue(true);

    const result1 = TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(result1).toBe(true);

    // Second check: not authenticated
    authService.isAuthenticated.and.returnValue(false);
    router.createUrlTree.and.returnValue({} as UrlTree);

    TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(router.createUrlTree).toHaveBeenCalled();
  });

  // ============================================
  // Integration Tests
  // ============================================

  it('should work with protected routes', () => {
    authService.isAuthenticated.and.returnValue(true);

    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(result).toBe(true);
    expect(router.createUrlTree).not.toHaveBeenCalled();
  });

  it('should only redirect once on failed authentication', () => {
    authService.isAuthenticated.and.returnValue(false);
    router.createUrlTree.and.returnValue({} as UrlTree);

    TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(router.createUrlTree).toHaveBeenCalledTimes(1);
  });

  // ============================================
  // Edge Cases
  // ============================================

  it('should handle guard on unauthenticated requests', () => {
    authService.isAuthenticated.and.returnValue(false);
    const mockUrlTree = {} as UrlTree;
    router.createUrlTree.and.returnValue(mockUrlTree);

    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(result).toEqual(mockUrlTree);
  });

  it('should handle rapid route changes', () => {
    authService.isAuthenticated.and.returnValue(true);

    for (let i = 0; i < 5; i++) {
      const result = TestBed.runInInjectionContext(() =>
        authGuard({} as any, () => null)
      );
      expect(result).toBe(true);
    }

    expect(authService.isAuthenticated).toHaveBeenCalledTimes(5);
  });

  // ============================================
  // Return Value Tests
  // ============================================

  it('should return true boolean when authenticated', () => {
    authService.isAuthenticated.and.returnValue(true);

    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(typeof result).toBe('boolean');
    expect(result).toBe(true);
  });

  it('should return UrlTree when not authenticated', () => {
    authService.isAuthenticated.and.returnValue(false);
    const mockUrlTree = { toString: () => '/login' } as UrlTree;
    router.createUrlTree.and.returnValue(mockUrlTree);

    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as any, () => null)
    );

    expect(result).toBe(mockUrlTree);
  });
});
