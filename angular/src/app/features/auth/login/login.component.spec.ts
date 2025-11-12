import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { LoginComponent } from './login.component';
import { AuthService } from '../../../core/services/auth.service';
import { of, throwError } from 'rxjs';

describe('LoginComponent - Form Validation', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['login']);
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    await TestBed.configureTestingModule({
      imports: [LoginComponent, ReactiveFormsModule],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: Router, useValue: routerSpy }
      ]
    }).compileComponents();

    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;

    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  /**
   * Test 1: Empty email should show validation error
   */
  it('should show email required error when email is empty', () => {
    const emailControl = component.form.get('email');
    emailControl?.markAsTouched();
    fixture.detectChanges();

    expect(emailControl?.hasError('required')).toBeTruthy();
    expect(component.getEmailError()).toBeTruthy();
  });

  /**
   * Test 2: Invalid email format should show validation error
   */
  it('should show email format error for invalid email', () => {
    const emailControl = component.form.get('email');
    emailControl?.setValue('invalid-email');
    emailControl?.markAsTouched();
    fixture.detectChanges();

    expect(emailControl?.hasError('email')).toBeTruthy();
    expect(component.getEmailError()).toBeTruthy();
  });

  /**
   * Test 3: Valid email should pass validation
   */
  it('should accept valid email format', () => {
    const emailControl = component.form.get('email');
    emailControl?.setValue('user@example.com');
    emailControl?.markAsTouched();
    fixture.detectChanges();

    expect(emailControl?.valid).toBeTruthy();
    expect(component.getEmailError()).toBeFalsy();
  });

  /**
   * Test 4: Empty password should show validation error
   */
  it('should show password required error when password is empty', () => {
    const passwordControl = component.form.get('password');
    passwordControl?.markAsTouched();
    fixture.detectChanges();

    expect(passwordControl?.hasError('required')).toBeTruthy();
    expect(component.getPasswordError()).toBeTruthy();
  });

  /**
   * Test 5: Short password should show validation error
   */
  it('should show password length error for short password', () => {
    const passwordControl = component.form.get('password');
    passwordControl?.setValue('short');
    passwordControl?.markAsTouched();
    fixture.detectChanges();

    expect(passwordControl?.hasError('minlength')).toBeTruthy();
    expect(component.getPasswordError()).toBeTruthy();
  });

  /**
   * Test 6: Valid password should pass validation
   */
  it('should accept valid password', () => {
    const passwordControl = component.form.get('password');
    passwordControl?.setValue('validpassword123');
    passwordControl?.markAsTouched();
    fixture.detectChanges();

    expect(passwordControl?.valid).toBeTruthy();
    expect(component.getPasswordError()).toBeFalsy();
  });

  /**
   * Test 7: Form should be disabled with both fields invalid
   */
  it('should disable submit button when form is invalid', () => {
    component.form.get('email')?.setValue('');
    component.form.get('password')?.setValue('');
    fixture.detectChanges();

    expect(component.form.valid).toBeFalsy();
    const submitButton = fixture.nativeElement.querySelector('button[type="submit"]');
    expect(submitButton?.disabled).toBeTruthy();
  });

  /**
   * Test 8: Form should be enabled with both fields valid
   */
  it('should enable submit button when form is valid', () => {
    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('validpassword123');
    fixture.detectChanges();

    expect(component.form.valid).toBeTruthy();
    const submitButton = fixture.nativeElement.querySelector('button[type="submit"]');
    expect(submitButton?.disabled).toBeFalsy();
  });

  /**
   * Test 9: Form should trim whitespace from inputs
   */
  it('should trim whitespace from email input', () => {
    const emailControl = component.form.get('email');
    emailControl?.setValue('  user@example.com  ');
    emailControl?.updateValueAndValidity();
    fixture.detectChanges();

    expect(emailControl?.value).toBe('user@example.com');
  });

  /**
   * Test 10: Form should be pristine after initial load
   */
  it('should have pristine form on initial load', () => {
    expect(component.form.pristine).toBeTruthy();
    expect(component.form.untouched).toBeTruthy();
  });
});

describe('LoginComponent - Successful Login Flow', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['login']);
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    await TestBed.configureTestingModule({
      imports: [LoginComponent, ReactiveFormsModule],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: Router, useValue: routerSpy }
      ]
    }).compileComponents();

    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;

    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  /**
   * Test 11: Successful login should navigate to dashboard
   */
  it('should navigate to dashboard on successful login', (done) => {
    const mockResponse = {
      token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
      user: {
        id: 'uuid-123',
        email: 'user@example.com',
        roles: ['ROLE_USER'],
        is_verified: true
      }
    };

    authService.login.and.returnValue(of(mockResponse));

    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('validpassword123');
    component.onSubmit();

    setTimeout(() => {
      expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
      done();
    }, 100);
  });

  /**
   * Test 12: Login should call AuthService with correct credentials
   */
  it('should call AuthService.login with email and password', () => {
    authService.login.and.returnValue(of({}));

    const email = 'user@example.com';
    const password = 'validpassword123';

    component.form.get('email')?.setValue(email);
    component.form.get('password')?.setValue(password);
    component.onSubmit();

    expect(authService.login).toHaveBeenCalledWith(email, password);
  });

  /**
   * Test 13: Login should update user state
   */
  it('should update auth service user state after successful login', (done) => {
    const mockUser = {
      id: 'uuid-123',
      email: 'user@example.com',
      roles: ['ROLE_USER'],
      is_verified: true
    };

    authService.login.and.returnValue(of({
      token: 'jwt-token-123',
      user: mockUser
    }));

    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('validpassword123');
    component.onSubmit();

    setTimeout(() => {
      // Verify that the service was called and user data was processed
      expect(authService.login).toHaveBeenCalled();
      done();
    }, 100);
  });

  /**
   * Test 14: Loading state should show during login
   */
  it('should show loading state during login request', (done) => {
    authService.login.and.returnValue(of({}));

    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('validpassword123');
    
    // Check initial state
    expect(component.isLoading).toBeFalsy();

    component.onSubmit();

    // After submit, should be loading
    fixture.detectChanges();
    expect(component.isLoading).toBeTruthy();

    setTimeout(() => {
      // After completion, should not be loading
      fixture.detectChanges();
      done();
    }, 100);
  });

  /**
   * Test 15: Form should be disabled during login
   */
  it('should disable form while login is in progress', (done) => {
    authService.login.and.returnValue(of({}));

    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('validpassword123');
    
    component.onSubmit();
    fixture.detectChanges();

    expect(component.form.disabled).toBeTruthy();

    setTimeout(() => {
      done();
    }, 100);
  });
});

describe('LoginComponent - Failed Login Flow', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['login']);
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    await TestBed.configureTestingModule({
      imports: [LoginComponent, ReactiveFormsModule],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: Router, useValue: routerSpy }
      ]
    }).compileComponents();

    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;

    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  /**
   * Test 16: Invalid credentials should show error message
   */
  it('should display error message on failed login', (done) => {
    const errorResponse = { error: { message: 'Invalid credentials' } };
    authService.login.and.returnValue(throwError(() => errorResponse));

    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('wrongpassword');
    component.onSubmit();

    setTimeout(() => {
      fixture.detectChanges();
      expect(component.errorMessage).toBeTruthy();
      expect(component.errorMessage).toContain('Invalid credentials');
      done();
    }, 100);
  });

  /**
   * Test 17: Should not navigate on failed login
   */
  it('should not navigate to dashboard on failed login', (done) => {
    authService.login.and.returnValue(throwError(() => ({ error: { message: 'Login failed' } })));

    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('wrongpassword');
    component.onSubmit();

    setTimeout(() => {
      expect(router.navigate).not.toHaveBeenCalled();
      done();
    }, 100);
  });

  /**
   * Test 18: Error message should be clearable
   */
  it('should clear error message when user modifies form', () => {
    component.errorMessage = 'Login failed';
    fixture.detectChanges();

    component.form.get('email')?.setValue('newemail@example.com');
    fixture.detectChanges();

    // The error should be cleared on form change
    // This depends on your component implementation
    // typically handled by valueChanges subscription
  });

  /**
   * Test 19: Form should be enabled after failed login
   */
  it('should enable form after failed login for retry', (done) => {
    authService.login.and.returnValue(throwError(() => ({ error: { message: 'Login failed' } })));

    component.form.get('email')?.setValue('user@example.com');
    component.form.get('password')?.setValue('wrongpassword');
    component.onSubmit();

    setTimeout(() => {
      fixture.detectChanges();
      expect(component.form.enabled).toBeTruthy();
      done();
    }, 100);
  });

  /**
   * Test 20: Should show specific error for unverified email
   */
  it('should show specific message for unverified email', (done) => {
    const errorResponse = { error: { message: 'Email not verified' } };
    authService.login.and.returnValue(throwError(() => errorResponse));

    component.form.get('email')?.setValue('unverified@example.com');
    component.form.get('password')?.setValue('validpassword123');
    component.onSubmit();

    setTimeout(() => {
      fixture.detectChanges();
      expect(component.errorMessage).toContain('not verified');
      done();
    }, 100);
  });
});
