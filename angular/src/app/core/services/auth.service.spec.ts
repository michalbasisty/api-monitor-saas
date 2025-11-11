import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { AuthService } from './auth.service';
import { User } from '../models/user.model';

describe('AuthService', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [AuthService]
    });
    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('register', () => {
    it('should register a new user', () => {
      const email = 'test@example.com';
      const password = 'password123';

      service.register(email, password).subscribe(response => {
        expect(response.id).toBeTruthy();
        expect(response.verification_token).toBeTruthy();
      });

      const req = httpMock.expectOne('/api/auth/register');
      expect(req.request.method).toBe('POST');
      expect(req.request.body.email).toBe(email);
      expect(req.request.body.password).toBe(password);

      req.flush({
        id: '123',
        email: email,
        verification_token: 'token123'
      });
    });

    it('should handle registration error', () => {
      service.register('test@example.com', 'pass').subscribe(
        () => fail('should have failed'),
        error => {
          expect(error.status).toBe(400);
        }
      );

      const req = httpMock.expectOne('/api/auth/register');
      req.flush('Email already exists', { status: 400, statusText: 'Bad Request' });
    });
  });

  describe('login', () => {
    it('should login user and return token', () => {
      const email = 'test@example.com';
      const password = 'password123';

      service.login(email, password).subscribe(response => {
        expect(response.token).toBeTruthy();
        expect(response.user.email).toBe(email);
      });

      const req = httpMock.expectOne('/api/auth/login');
      expect(req.request.method).toBe('POST');

      req.flush({
        token: 'eyJ0eXAiOiJKV1QiLCJhbGc...',
        user: { id: '123', email: email, roles: ['ROLE_USER'] }
      });
    });

    it('should store token in localStorage on successful login', () => {
      spyOn(localStorage, 'setItem');

      service.login('test@example.com', 'password123').subscribe();

      const req = httpMock.expectOne('/api/auth/login');
      req.flush({ token: 'token123', user: { id: '123', email: 'test@example.com' } });

      expect(localStorage.setItem).toHaveBeenCalledWith('token', 'token123');
    });

    it('should handle login error', () => {
      service.login('test@example.com', 'wrong').subscribe(
        () => fail('should have failed'),
        error => {
          expect(error.status).toBe(401);
        }
      );

      const req = httpMock.expectOne('/api/auth/login');
      req.flush('Invalid credentials', { status: 401, statusText: 'Unauthorized' });
    });
  });

  describe('verifyEmail', () => {
    it('should verify email with token', () => {
      const token = 'verify-token-123';

      service.verifyEmail(token).subscribe(response => {
        expect(response.user.is_verified).toBe(true);
      });

      const req = httpMock.expectOne(`/api/auth/verify-email/${token}`);
      expect(req.request.method).toBe('GET');

      req.flush({
        message: 'Email verified',
        user: { id: '123', email: 'test@example.com', is_verified: true }
      });
    });
  });

  describe('logout', () => {
    it('should clear token from localStorage', () => {
      spyOn(localStorage, 'removeItem');

      service.logout();

      expect(localStorage.removeItem).toHaveBeenCalledWith('token');
    });
  });

  describe('getToken', () => {
    it('should retrieve token from localStorage', () => {
      spyOn(localStorage, 'getItem').and.returnValue('token123');

      const token = service.getToken();

      expect(token).toBe('token123');
      expect(localStorage.getItem).toHaveBeenCalledWith('token');
    });

    it('should return null if token does not exist', () => {
      spyOn(localStorage, 'getItem').and.returnValue(null);

      const token = service.getToken();

      expect(token).toBeNull();
    });
  });

  describe('isAuthenticated', () => {
    it('should return true if token exists', () => {
      spyOn(localStorage, 'getItem').and.returnValue('token123');

      const isAuth = service.isAuthenticated();

      expect(isAuth).toBe(true);
    });

    it('should return false if token does not exist', () => {
      spyOn(localStorage, 'getItem').and.returnValue(null);

      const isAuth = service.isAuthenticated();

      expect(isAuth).toBe(false);
    });
  });
});
