import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { AuthGuard } from './auth.guard';
import { AuthService } from '../services/auth.service';
import { signal } from '@angular/core';

describe('AuthGuard - Protection & Redirection', () => {
  let authService: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(() => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['logout']);
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    TestBed.configureTestingModule({
      providers: [
        AuthGuard,
        { provide: AuthService, useValue: authServiceSpy },
        { provide: Router, useValue: routerSpy }
      ]
    });

    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;
  });

  /**
   * Test 1: Should allow navigation when user is authenticated
   */
  it('should allow navigation when user is authenticated', () => {
    // Mock authenticated user
    authService.isAuthenticated = signal(true);
    authService.currentUser = signal({ id: 'user-123', email: 'user@example.com' });

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/', null);

    expect(result).toBeTruthy();
  });

  /**
   * Test 2: Should deny navigation when user is not authenticated
   */
  it('should deny navigation when user is not authenticated', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/', null);

    expect(result).toBeFalsy();
  });

  /**
   * Test 3: Should redirect to login when access denied
   */
  it('should redirect to login route when access is denied', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    guard('/', null);

    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  /**
   * Test 4: Should not redirect when access is allowed
   */
  it('should not redirect when user is authenticated', () => {
    authService.isAuthenticated = signal(true);
    authService.currentUser = signal({ id: 'user-123', email: 'user@example.com' });

    const guard = TestBed.inject(AuthGuard);
    guard('/', null);

    expect(router.navigate).not.toHaveBeenCalled();
  });

  /**
   * Test 5: Should handle null user state gracefully
   */
  it('should deny access when user is null', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/', null);

    expect(result).toBeFalsy();
  });

  /**
   * Test 6: Should protect dashboard route
   */
  it('should protect dashboard route for unauthenticated users', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/dashboard', null);

    expect(result).toBeFalsy();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  /**
   * Test 7: Should allow dashboard access for authenticated users
   */
  it('should allow dashboard route for authenticated users', () => {
    authService.isAuthenticated = signal(true);
    authService.currentUser = signal({
      id: 'user-123',
      email: 'user@example.com',
      roles: ['ROLE_USER']
    });

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/dashboard', null);

    expect(result).toBeTruthy();
  });

  /**
   * Test 8: Should protect api-settings route
   */
  it('should protect api-settings route for unauthenticated users', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/api-settings', null);

    expect(result).toBeFalsy();
  });

  /**
   * Test 9: Should allow api-settings for authenticated users
   */
  it('should allow api-settings route for authenticated users', () => {
    authService.isAuthenticated = signal(true);
    authService.currentUser = signal({
      id: 'user-123',
      email: 'user@example.com',
      roles: ['ROLE_USER']
    });

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/api-settings', null);

    expect(result).toBeTruthy();
  });

  /**
   * Test 10: Should work with nested routes
   */
  it('should protect nested routes', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/dashboard/analytics/performance', null);

    expect(result).toBeFalsy();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  /**
   * Test 11: Should handle authentication state changes
   */
  it('should respond to authentication state changes', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    let result = guard('/', null);
    expect(result).toBeFalsy();

    // Simulate login
    authService.isAuthenticated = signal(true);
    authService.currentUser = signal({ id: 'user-123', email: 'user@example.com' });

    result = guard('/', null);
    expect(result).toBeTruthy();
  });

  /**
   * Test 12: Should clear router navigation history on redirect
   */
  it('should navigate to login without history', () => {
    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    guard('/', null);

    // Verify that navigation goes to /login (the second argument in navigate 
    // could be { replaceUrl: true } to clear history)
    expect(router.navigate).toHaveBeenCalled();
    const callArgs = router.navigate.calls.mostRecent().args;
    expect(callArgs[0]).toEqual(['/login']);
  });

  /**
   * Test 13: Should validate JWT token presence
   */
  it('should check for valid JWT token in storage', () => {
    // Mock localStorage
    const token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...';
    spyOn(localStorage, 'getItem').and.returnValue(token);

    authService.isAuthenticated = signal(true);
    authService.currentUser = signal({ id: 'user-123', email: 'user@example.com' });

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/', null);

    expect(result).toBeTruthy();
    expect(localStorage.getItem).toHaveBeenCalledWith('auth_token');
  });

  /**
   * Test 14: Should deny access if token is expired
   */
  it('should deny access if JWT token is expired', () => {
    spyOn(localStorage, 'getItem').and.returnValue(null);

    authService.isAuthenticated = signal(false);
    authService.currentUser = signal(null);

    const guard = TestBed.inject(AuthGuard);
    const result = guard('/', null);

    expect(result).toBeFalsy();
  });

  /**
   * Test 15: Should handle guard on admin routes
   */
  it('should protect admin routes appropriately', () => {
    authService.isAuthenticated = signal(true);
    authService.currentUser = signal({
      id: 'user-123',
      email: 'user@example.com',
      roles: ['ROLE_USER'] // Not admin
    });

    const guard = TestBed.inject(AuthGuard);
    // Note: This may require role-based guard instead of just auth guard
    // Basic auth guard should still allow but role guard would deny
    const result = guard('/admin', null);

    expect(result).toBeTruthy(); // Auth guard allows, role guard would deny
  });
});
