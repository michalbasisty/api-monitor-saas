import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { catchError, throwError } from 'rxjs';

export interface ApiError {
  message: string;
  status: number;
  timestamp: string;
  path?: string;
}

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const authService = inject(AuthService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      console.error('Error interceptor caught error:', error.status, error);
      const apiError = parseError(error);
      handleError(apiError, authService, router);
      return throwError(() => apiError);
    })
  );
};

function parseError(error: HttpErrorResponse): ApiError {
  let message = 'An unexpected error occurred';

  if (error.error instanceof ErrorEvent) {
    // Client-side error
    message = error.error.message;
  } else {
    // Server-side error
    message =
      error.error?.message ||
      error.error?.error ||
      error.statusText ||
      message;
  }

  return {
    message,
    status: error.status,
    timestamp: new Date().toISOString(),
    path: error.url || undefined,
  };
}

function handleError(
  error: ApiError,
  authService: AuthService,
  router: Router
): void {
  console.error('HTTP Error:', error);

  switch (error.status) {
    case 401:
      // Unauthorized - clear auth and redirect to login
      authService.logout();
      router.navigate(['/login']);
      break;

    case 403:
      // Forbidden
      console.error('Access denied:', error.message);
      router.navigate(['/unauthorized']);
      break;

    case 404:
      // Not found
      console.warn('Resource not found:', error.path);
      break;

    case 409:
      // Conflict (e.g., email already exists)
      console.warn('Conflict:', error.message);
      break;

    case 422:
      // Validation error
      console.warn('Validation error:', error.message);
      break;

    case 500:
    case 502:
    case 503:
      // Server errors
      console.error('Server error:', error.status, error.message);
      router.navigate(['/error'], {
        queryParams: { code: error.status },
      });
      break;

    case 0:
      // Network error
      console.error('Network error - check if server is running');
      break;

    default:
      console.error(`Error ${error.status}:`, error.message);
  }
}
