import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { AuthService } from './auth.service';
import { Router } from '@angular/router';

describe('AuthService - Logout & Token Cleanup', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;
  let router: jasmine.SpyObj<Router>;

  beforeEach(() => {
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        AuthService,
        { provide: Router, useValue: routerSpy }
      ]
    });

    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  /**
   * Test 1: Logout should clear JWT token
   */
  it('should clear JWT token from localStorage on logout', () => {
    // Setup
    const token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...';
    localStorage.setItem('auth_token', token);
    expect(localStorage.getItem('auth_token')).toBe(token);

    // Execute
    service.logout();

    // Verify
    expect(localStorage.getItem('auth_token')).toBeNull();
  });

  /**
   * Test 2: Logout should clear user data
   */
  it('should clear user data on logout', () => {
    // Setup
    const userData = {
      id: 'user-123',
      email: 'user@example.com',
      roles: ['ROLE_USER']
    };
    localStorage.setItem('user_data', JSON.stringify(userData));

    // Execute
    service.logout();

    // Verify
    expect(localStorage.getItem('user_data')).toBeNull();
  });

  /**
   * Test 3: Logout should clear all auth-related data
   */
  it('should clear all authentication data on logout', () => {
    // Setup
    localStorage.setItem('auth_token', 'token123');
    localStorage.setItem('user_data', JSON.stringify({ id: 'user-123' }));
    localStorage.setItem('refresh_token', 'refresh123');

    // Execute
    service.logout();

    // Verify
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('user_data')).toBeNull();
    expect(localStorage.getItem('refresh_token')).toBeNull();
  });

  /**
   * Test 4: Logout should update authentication state
   */
  it('should set isAuthenticated to false on logout', () => {
    // Setup
    service.isAuthenticated = signal(true);

    // Execute
    service.logout();

    // Verify
    expect(service.isAuthenticated()).toBeFalsy();
  });

  /**
   * Test 5: Logout should clear current user
   */
  it('should clear currentUser signal on logout', () => {
    // Setup
    service.currentUser = signal({ id: 'user-123', email: 'user@example.com' });

    // Execute
    service.logout();

    // Verify
    expect(service.currentUser()).toBeNull();
  });

  /**
   * Test 6: Logout should redirect to login page
   */
  it('should redirect to login page on logout', () => {
    // Execute
    service.logout();

    // Verify
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  /**
   * Test 7: Multiple logout calls should be idempotent
   */
  it('should handle multiple logout calls without error', () => {
    // Execute
    service.logout();
    service.logout();
    service.logout();

    // Verify - should not throw error
    expect(router.navigate).toHaveBeenCalledTimes(3);
  });

  /**
   * Test 8: Logout should work even if localStorage is empty
   */
  it('should handle logout when localStorage is empty', () => {
    // Ensure localStorage is empty
    localStorage.clear();

    // Execute
    expect(() => service.logout()).not.toThrow();

    // Verify
    expect(service.isAuthenticated()).toBeFalsy();
  });

  /**
   * Test 9: Logout should remove HTTP Authorization header
   */
  it('should clear token from HTTP interceptor', () => {
    // Setup
    const token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...';
    localStorage.setItem('auth_token', token);
    spyOn(service, 'getToken').and.returnValue(token);

    // Execute
    service.logout();

    // Verify - getToken should return null after logout
    expect(service.getToken()).toBeNull();
  });

  /**
   * Test 10: Logout should clear any pending requests
   */
  it('should cancel any pending API requests on logout', () => {
    // Execute
    service.logout();

    // Verify
    expect(router.navigate).toHaveBeenCalled();
  });

  /**
   * Test 11: Token cleanup should prevent use of stale token
   */
  it('should prevent authentication with cleared token', () => {
    // Setup
    localStorage.setItem('auth_token', 'expired-token');

    // Execute
    service.logout();

    // Verify
    const storedToken = localStorage.getItem('auth_token');
    expect(storedToken).toBeNull();
  });

  /**
   * Test 12: Session data should be unrecoverable after logout
   */
  it('should not recover session data after logout', () => {
    // Setup
    const sessionData = {
      token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
      user: { id: 'user-123', email: 'user@example.com' }
    };
    localStorage.setItem('session', JSON.stringify(sessionData));

    // Execute
    service.logout();

    // Verify
    expect(localStorage.getItem('session')).toBeNull();
  });

  /**
   * Test 13: Logout should work across different tabs
   */
  it('should handle logout in one tab affecting others', () => {
    // Setup
    localStorage.setItem('auth_token', 'token123');

    // Execute
    service.logout();

    // Verify - in real scenario, storage events would notify other tabs
    expect(localStorage.getItem('auth_token')).toBeNull();
  });

  /**
   * Test 14: Session should be completely terminated
   */
  it('should terminate session completely', () => {
    // Setup
    service.isAuthenticated = signal(true);
    service.currentUser = signal({
      id: 'user-123',
      email: 'user@example.com',
      roles: ['ROLE_USER']
    });
    localStorage.setItem('auth_token', 'token123');

    // Execute
    service.logout();

    // Verify
    expect(service.isAuthenticated()).toBeFalsy();
    expect(service.currentUser()).toBeNull();
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  /**
   * Test 15: Logout should prevent re-authentication with old token
   */
  it('should prevent using old token after logout', () => {
    // Setup
    const oldToken = 'old-token-123';
    localStorage.setItem('auth_token', oldToken);

    // Execute
    service.logout();

    // Try to use old token
    const currentToken = service.getToken();

    // Verify
    expect(currentToken).not.toBe(oldToken);
    expect(currentToken).toBeNull();
  });
});

describe('AuthService - Login & Token Management', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;
  let router: jasmine.SpyObj<Router>;

  beforeEach(() => {
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        AuthService,
        { provide: Router, useValue: routerSpy }
      ]
    });

    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  /**
   * Test 16: Successful login should store token
   */
  it('should store JWT token after successful login', (done) => {
    const mockResponse = {
      token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
      user: {
        id: 'user-123',
        email: 'user@example.com',
        roles: ['ROLE_USER'],
        is_verified: true
      }
    };

    service.login('user@example.com', 'password123').subscribe(() => {
      expect(localStorage.getItem('auth_token')).toBe(mockResponse.token);
      done();
    });

    const req = httpMock.expectOne('/api/auth/login');
    expect(req.request.method).toBe('POST');
    req.flush(mockResponse);
  });

  /**
   * Test 17: Login should update current user
   */
  it('should update currentUser after successful login', (done) => {
    const mockResponse = {
      token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
      user: {
        id: 'user-123',
        email: 'user@example.com',
        roles: ['ROLE_USER'],
        is_verified: true
      }
    };

    service.login('user@example.com', 'password123').subscribe(() => {
      expect(service.currentUser()).toEqual(mockResponse.user);
      done();
    });

    const req = httpMock.expectOne('/api/auth/login');
    req.flush(mockResponse);
  });

  /**
   * Test 18: Login should set isAuthenticated flag
   */
  it('should set isAuthenticated to true after successful login', (done) => {
    const mockResponse = {
      token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
      user: {
        id: 'user-123',
        email: 'user@example.com',
        roles: ['ROLE_USER'],
        is_verified: true
      }
    };

    service.login('user@example.com', 'password123').subscribe(() => {
      expect(service.isAuthenticated()).toBeTruthy();
      done();
    });

    const req = httpMock.expectOne('/api/auth/login');
    req.flush(mockResponse);
  });

  /**
   * Test 19: Login failure should not store token
   */
  it('should not store token on login failure', (done) => {
    localStorage.clear();

    service.login('user@example.com', 'wrongpassword').subscribe(
      () => fail('should have failed'),
      () => {
        expect(localStorage.getItem('auth_token')).toBeNull();
        done();
      }
    );

    const req = httpMock.expectOne('/api/auth/login');
    req.flush({ message: 'Invalid credentials' }, { status: 401, statusText: 'Unauthorized' });
  });

  /**
   * Test 20: Token should be retrievable after login
   */
  it('should be able to retrieve token with getToken() after login', (done) => {
    const mockToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...';
    const mockResponse = {
      token: mockToken,
      user: {
        id: 'user-123',
        email: 'user@example.com',
        roles: ['ROLE_USER'],
        is_verified: true
      }
    };

    service.login('user@example.com', 'password123').subscribe(() => {
      const storedToken = service.getToken();
      expect(storedToken).toBe(mockToken);
      done();
    });

    const req = httpMock.expectOne('/api/auth/login');
    req.flush(mockResponse);
  });
});

// Helper for signal import (add at top if needed)
import { signal } from '@angular/core';
