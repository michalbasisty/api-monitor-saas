import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./features/auth/login/login.component').then(m => m.LoginComponent)
  },
  {
    path: 'register',
    loadComponent: () => import('./features/auth/register/register.component').then(m => m.RegisterComponent)
  },
  {
    path: '',
    canActivate: [authGuard],
    children: [
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/dashboard.component').then(m => m.DashboardComponent)
      },
      {
        path: 'endpoints',
        loadComponent: () => import('./features/endpoints/endpoint-list/endpoint-list.component').then(m => m.EndpointListComponent)
      },
      {
        path: 'endpoints/new',
        loadComponent: () => import('./features/endpoints/endpoint-form/endpoint-form.component').then(m => m.EndpointFormComponent)
      },
      {
        path: 'endpoints/:id',
        loadComponent: () => import('./features/endpoints/endpoint-detail/endpoint-detail.component').then(m => m.EndpointDetailComponent)
      },
      {
        path: 'endpoints/:id/edit',
        loadComponent: () => import('./features/endpoints/endpoint-form/endpoint-form.component').then(m => m.EndpointFormComponent)
      },
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full'
      }
    ]
  },
  {
    path: '**',
    redirectTo: 'dashboard'
  }
];
