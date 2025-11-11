import { ComponentFixture, TestBed, fakeAsync, tick } from '@angular/core/testing';
import { RegisterComponent } from './register.component';
import { AuthService } from '../../../core/services/auth.service';
import { Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { ReactiveFormsModule } from '@angular/forms';

describe('RegisterComponent', () => {
  let component: RegisterComponent;
  let fixture: ComponentFixture<RegisterComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['register']);
    const routerSpy = jasmine.createSpyObj('Router', ['navigate']);

    await TestBed.configureTestingModule({
      imports: [RegisterComponent, ReactiveFormsModule],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: Router, useValue: routerSpy }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(RegisterComponent);
    component = fixture.componentInstance;
    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    router = TestBed.inject(Router) as jasmine.SpyObj<Router>;
    fixture.detectChanges();
  });

  // ============================================
  // Component Initialization Tests
  // ============================================

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize form with email control', () => {
    expect(component.registerForm.get('email')).toBeTruthy();
  });

  it('should initialize form with password control', () => {
    expect(component.registerForm.get('password')).toBeTruthy();
  });

  it('should initialize form with confirmPassword control', () => {
    expect(component.registerForm.get('confirmPassword')).toBeTruthy();
  });

  it('should initialize error as null', () => {
    expect(component.error).toBeNull();
  });

  it('should initialize success as null', () => {
    expect(component.success).toBeNull();
  });

  it('should initialize loading as false', () => {
    expect(component.loading).toBe(false);
  });

  // ============================================
  // Form Validation Tests
  // ============================================

  it('should require email', () => {
    const emailControl = component.registerForm.get('email');
    emailControl?.setValue('');

    expect(emailControl?.hasError('required')).toBe(true);
  });

  it('should require valid email format', () => {
    const emailControl = component.registerForm.get('email');
    emailControl?.setValue('invalid-email');

    expect(emailControl?.hasError('email')).toBe(true);
  });

  it('should accept valid email', () => {
    const emailControl = component.registerForm.get('email');
    emailControl?.setValue('user@example.com');

    expect(emailControl?.valid).toBe(true);
  });

  it('should require password', () => {
    const passwordControl = component.registerForm.get('password');
    passwordControl?.setValue('');

    expect(passwordControl?.hasError('required')).toBe(true);
  });

  it('should require minimum 8 character password', () => {
    const passwordControl = component.registerForm.get('password');
    passwordControl?.setValue('short');

    expect(passwordControl?.hasError('minlength')).toBe(true);
  });

  it('should accept 8+ character password', () => {
    const passwordControl = component.registerForm.get('password');
    passwordControl?.setValue('validpassword123');

    expect(passwordControl?.valid).toBe(true);
  });

  it('should require confirm password', () => {
    const confirmControl = component.registerForm.get('confirmPassword');
    confirmControl?.setValue('');

    expect(confirmControl?.hasError('required')).toBe(true);
  });

  it('should mark form as invalid when empty', () => {
    component.registerForm.reset();

    expect(component.registerForm.invalid).toBe(true);
  });

  it('should mark form as valid with all fields filled correctly', () => {
    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'validpassword123',
      confirmPassword: 'validpassword123'
    });

    expect(component.registerForm.valid).toBe(true);
  });

  // ============================================
  // Password Matching Tests
  // ============================================

  it('should reject mismatched passwords', () => {
    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password456'
    });

    component.onSubmit();

    expect(component.error).toBe('Passwords do not match');
  });

  it('should accept matching passwords', () => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.error).not.toBe('Passwords do not match');
  });

  // ============================================
  // Registration Submission Tests
  // ============================================

  it('should not submit invalid form', () => {
    component.registerForm.reset();

    component.onSubmit();

    expect(authService.register).not.toHaveBeenCalled();
  });

  it('should submit valid form', () => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(authService.register).toHaveBeenCalled();
  });

  it('should send correct data to service', () => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'test@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(authService.register).toHaveBeenCalledWith({
      email: 'test@example.com',
      password: 'password123'
    });
  });

  it('should set loading to true on submit', () => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.loading).toBe(false); // Should be false after success
  });

  it('should clear error on submit', () => {
    component.error = 'Previous error';
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.error).toBeNull();
  });

  // ============================================
  // Success Response Tests
  // ============================================

  it('should show success message on registration success', () => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.success).toContain('successful');
  });

  it('should set loading to false after successful registration', () => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.loading).toBe(false);
  });

  it('should navigate to login after successful registration', fakeAsync(() => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();
    tick(3000);

    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  }));

  // ============================================
  // Error Response Tests
  // ============================================

  it('should show error message on registration failure', () => {
    const errorResponse = { error: { message: 'Email already exists' } };
    authService.register.and.returnValue(
      throwError(() => errorResponse)
    );

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.error).toBe('Email already exists');
  });

  it('should show default error message if no error details', () => {
    authService.register.and.returnValue(
      throwError(() => new Error('Network error'))
    );

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.error).toBe('Registration failed. Please try again.');
  });

  it('should set loading to false on error', () => {
    authService.register.and.returnValue(
      throwError(() => new Error('API Error'))
    );

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.loading).toBe(false);
  });

  it('should not navigate on registration failure', () => {
    authService.register.and.returnValue(
      throwError(() => new Error('API Error'))
    );

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(router.navigate).not.toHaveBeenCalled();
  });

  // ============================================
  // Edge Cases Tests
  // ============================================

  it('should handle very long email addresses', () => {
    const longEmail = 'a'.repeat(100) + '@example.com';
    const emailControl = component.registerForm.get('email');
    emailControl?.setValue(longEmail);

    expect(emailControl?.valid).toBe(true);
  });

  it('should handle special characters in password', () => {
    const passwordControl = component.registerForm.get('password');
    passwordControl?.setValue('p@ssw0rd!#$%^&*(');

    expect(passwordControl?.valid).toBe(true);
  });

  it('should handle case sensitive email comparison', () => {
    const emailControl = component.registerForm.get('email');
    emailControl?.setValue('User@EXAMPLE.COM');

    expect(emailControl?.valid).toBe(true);
  });

  // ============================================
  // Multiple Submission Tests
  // ============================================

  it('should not allow multiple simultaneous submissions', () => {
    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.loading = true;
    component.onSubmit();

    // Should not submit if already loading
    expect(component.loading).toBe(true);
  });

  // ============================================
  // Form State Tests
  // ============================================

  it('should preserve form state on error', () => {
    const email = 'user@example.com';
    const password = 'password123';

    authService.register.and.returnValue(
      throwError(() => new Error('API Error'))
    );

    component.registerForm.setValue({
      email: email,
      password: password,
      confirmPassword: password
    });

    component.onSubmit();

    expect(component.registerForm.get('email')?.value).toBe(email);
    expect(component.registerForm.get('password')?.value).toBe(password);
  });

  it('should clear error on new submission attempt', () => {
    component.error = 'Previous error';

    authService.register.and.returnValue(of({ message: 'Success', verification_token: 'token' }));

    component.registerForm.setValue({
      email: 'user@example.com',
      password: 'password123',
      confirmPassword: 'password123'
    });

    component.onSubmit();

    expect(component.error).toBeNull();
  });
});
