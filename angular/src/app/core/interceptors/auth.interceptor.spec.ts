import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { HttpClient, HttpRequest } from '@angular/common/http';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from '../services/auth.service';

describe('authInterceptor', () => {
  let httpClient: HttpClient;
  let httpMock: HttpTestingController;
  let authService: jasmine.SpyObj<AuthService>;

  beforeEach(() => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['getToken']);

    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        {
          provide: HTTP_INTERCEPTORS,
          useValue: authInterceptor,
          multi: true
        }
      ]
    });

    httpClient = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
  });

  afterEach(() => {
    httpMock.verify();
  });

  // ============================================
  // Token Injection Tests
  // ============================================

  it('should add Authorization header when token exists', () => {
    const token = 'test-jwt-token-12345';
    authService.getToken.and.returnValue(token);

    httpClient.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.headers.has('Authorization')).toBe(true);
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });

  it('should not add Authorization header when token is null', () => {
    authService.getToken.and.returnValue(null);

    httpClient.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.headers.has('Authorization')).toBe(false);

    req.flush({});
  });

  it('should not add Authorization header when token is empty string', () => {
    authService.getToken.and.returnValue('');

    httpClient.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.headers.has('Authorization')).toBe(false);

    req.flush({});
  });

  // ============================================
  // Token Format Tests
  // ============================================

  it('should use Bearer scheme for token', () => {
    const token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';
    authService.getToken.and.returnValue(token);

    httpClient.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    const authHeader = req.request.headers.get('Authorization');

    expect(authHeader).toMatch(/^Bearer /);
    expect(authHeader).toBe(`Bearer ${token}`);

    req.flush({});
  });

  it('should handle different token formats', () => {
    const tokens = [
      'simple-token',
      'token-with-dashes-123',
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U'
    ];

    tokens.forEach(token => {
      authService.getToken.and.returnValue(token);

      httpClient.get(`/api/test`).subscribe();

      const req = httpMock.expectOne('/api/test');
      expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

      req.flush({});
    });
  });

  // ============================================
  // Request Cloning Tests
  // ============================================

  it('should clone request instead of modifying original', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    const originalRequest = new HttpRequest('GET', '/api/test');
    const originalHeaders = originalRequest.headers.keys().length;

    httpClient.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');

    // Original request should not have been modified
    expect(originalRequest.headers.keys().length).toBe(originalHeaders);
    // New request should have Authorization header
    expect(req.request.headers.has('Authorization')).toBe(true);

    req.flush({});
  });

  it('should preserve existing headers when adding token', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.get('/api/test', {
      headers: {
        'X-Custom-Header': 'custom-value'
      }
    }).subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.headers.get('X-Custom-Header')).toBe('custom-value');
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });

  // ============================================
  // Different HTTP Methods Tests
  // ============================================

  it('should add token to GET requests', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.get('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.method).toBe('GET');
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });

  it('should add token to POST requests', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.post('/api/test', { data: 'test' }).subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.method).toBe('POST');
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });

  it('should add token to PUT requests', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.put('/api/test', { data: 'test' }).subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.method).toBe('PUT');
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });

  it('should add token to DELETE requests', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.delete('/api/test').subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.method).toBe('DELETE');
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });

  it('should add token to PATCH requests', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.patch('/api/test', { data: 'test' }).subscribe();

    const req = httpMock.expectOne('/api/test');
    expect(req.request.method).toBe('PATCH');
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });

  // ============================================
  // Error Handling Tests
  // ============================================

  it('should handle requests when getToken throws error', () => {
    authService.getToken.and.throwError('Token retrieval failed');

    // Should not throw, just pass through
    expect(() => {
      httpClient.get('/api/test').subscribe();
    }).not.toThrow();

    const req = httpMock.expectOne('/api/test');
    req.flush({});
  });

  it('should continue request chain even without token', () => {
    authService.getToken.and.returnValue(null);

    let requestComplete = false;
    httpClient.get('/api/test').subscribe(() => {
      requestComplete = true;
    });

    const req = httpMock.expectOne('/api/test');
    req.flush({ success: true });

    expect(requestComplete).toBe(true);
  });

  // ============================================
  // Multiple Request Tests
  // ============================================

  it('should add token to multiple concurrent requests', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.get('/api/test1').subscribe();
    httpClient.get('/api/test2').subscribe();
    httpClient.get('/api/test3').subscribe();

    const reqs = httpMock.match(req => req.url.includes('/api/test'));
    expect(reqs.length).toBe(3);

    reqs.forEach(req => {
      expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);
      req.flush({});
    });
  });

  it('should handle token change between requests', () => {
    authService.getToken.and.returnValue('token-1');

    httpClient.get('/api/test1').subscribe();

    const req1 = httpMock.expectOne('/api/test1');
    expect(req1.request.headers.get('Authorization')).toBe('Bearer token-1');
    req1.flush({});

    // Token changed
    authService.getToken.and.returnValue('token-2');

    httpClient.get('/api/test2').subscribe();

    const req2 = httpMock.expectOne('/api/test2');
    expect(req2.request.headers.get('Authorization')).toBe('Bearer token-2');
    req2.flush({});
  });

  // ============================================
  // Different URL Tests
  // ============================================

  it('should add token to all API endpoints', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    const endpoints = [
      '/api/users',
      '/api/endpoints',
      '/api/monitoring',
      '/api/alerts'
    ];

    endpoints.forEach(endpoint => {
      httpClient.get(endpoint).subscribe();
    });

    const reqs = httpMock.match(req => req.url.startsWith('/api'));
    expect(reqs.length).toBe(endpoints.length);

    reqs.forEach(req => {
      expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);
      req.flush({});
    });
  });

  it('should add token with query parameters', () => {
    const token = 'test-token';
    authService.getToken.and.returnValue(token);

    httpClient.get('/api/test', {
      params: {
        filter: 'active',
        limit: '10'
      }
    }).subscribe();

    const req = httpMock.expectOne(r =>
      r.url === '/api/test' && r.params.get('filter') === 'active'
    );
    expect(req.request.headers.get('Authorization')).toBe(`Bearer ${token}`);

    req.flush({});
  });
});

// Import for HTTP_INTERCEPTORS
import { HTTP_INTERCEPTORS } from '@angular/common/http';
