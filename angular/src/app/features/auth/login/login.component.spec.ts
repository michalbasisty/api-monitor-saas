import { TestBed, ComponentFixture } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { Router } from '@angular/router';
import { LoginComponent } from './login.component';
import { AuthService } from '../../../core/services/auth.service';
import { of, throwError } from 'rxjs';

describe('LoginComponent', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['login']);
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    await TestBed.configureTestingModule({
      declarations: [LoginComponent],
      imports: [ReactiveFormsModule, HttpClientTestingModule],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: Router, useValue: routerSpy }
      ]
    }).compileComponents();

    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;
    
    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  describe('form validation', () => {
    it('should have invalid form when empty', () => {
      fixture.detectChanges();
      
      const form = component.loginForm;
      expect(form.valid).toBeFalsy();
    });

    it('should require email', () => {
      fixture.detectChanges();
      
      const emailControl = component.loginForm.get('email');
      emailControl?.setValue('');
      
      expect(emailControl?.hasError('required')).toBeTruthy();
    });

    it('should require valid email format', () => {
      fixture.detectChanges();
      
      const emailControl = component.loginForm.get('email');
      emailControl?.setValue('invalid-email');
      
      expect(emailControl?.hasError('email')).toBeTruthy();
    });

    it('should require password', () => {
      fixture.detectChanges();
      
      const passwordControl = component.loginForm.get('password');
      passwordControl?.setValue('');
      
      expect(passwordControl?.hasError('required')).toBeTruthy();
    });

    it('should be valid with correct inputs', () => {
      fixture.detectChanges();
      
      component.loginForm.patchValue({
        email: 'test@example.com',
        password: 'password123'
      });
      
      expect(component.loginForm.valid).toBeTruthy();
    });
  });

  describe('login', () => {
    it('should call authService.login with form values', () => {
      fixture.detectChanges();
      
      const credentials = {
        email: 'test@example.com',
        password: 'password123'
      };
      
      component.loginForm.patchValue(credentials);
      authService.login.and.returnValue(of({
        token: 'token123',
        user: { id: '1', email: credentials.email, roles: ['ROLE_USER'] }
      }));
      
      component.login();
      
      expect(authService.login).toHaveBeenCalledWith(credentials.email, credentials.password);
    });

    it('should navigate to dashboard on successful login', () => {
      fixture.detectChanges();
      
      component.loginForm.patchValue({
        email: 'test@example.com',
        password: 'password123'
      });
      
      authService.login.and.returnValue(of({
        token: 'token123',
        user: { id: '1', email: 'test@example.com', roles: ['ROLE_USER'] }
      }));
      
      component.login();
      
      expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
    });

    it('should display error on login failure', () => {
      fixture.detectChanges();
      
      component.loginForm.patchValue({
        email: 'test@example.com',
        password: 'wrongpassword'
      });
      
      authService.login.and.returnValue(
        throwError(() => ({ message: 'Invalid credentials' }))
      );
      
      component.login();
      
      // Error should be captured
      expect(component.errorMessage).toBeTruthy();
    });

    it('should disable submit button while loading', () => {
      fixture.detectChanges();
      
      component.isLoading = true;
      fixture.detectChanges();
      
      const submitButton = fixture.nativeElement.querySelector('button[type="submit"]');
      expect(submitButton?.disabled).toBeTruthy();
    });
  });

  describe('registration link', () => {
    it('should have link to registration page', () => {
      fixture.detectChanges();
      
      const registerLink = fixture.nativeElement.querySelector('a[href="/register"]');
      expect(registerLink).toBeTruthy();
    });
  });
});
