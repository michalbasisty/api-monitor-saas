import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { NavbarComponent } from '../../shared/navbar/navbar.component';
import { EndpointService } from '../../core/services/endpoint.service';
import { Endpoint } from '../../core/models/endpoint.model';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink, NavbarComponent],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.css'
})
/**
 * Dashboard component displaying endpoint list and derived metrics.
 * Loads endpoints on init and exposes aggregate getters for the view.
 */
export class DashboardComponent implements OnInit {
  endpoints: Endpoint[] = [];
  loading = true;
  error: string | null = null;

  constructor(private endpointService: EndpointService) {}

  ngOnInit(): void {
    this.loadEndpoints();
  }

  /**
   * Load endpoints and update loading/error states.
   * Sets endpoints on success; records a user-friendly error message on failure.
   */
  loadEndpoints(): void {
    this.endpointService.getEndpoints().subscribe({
      next: (response) => {
        this.endpoints = response.endpoints;
        this.loading = false;
      },
      error: (err) => {
        this.error = 'Failed to load endpoints';
        this.loading = false;
      }
    });
  }

  /** Count of active endpoints. */
  get activeEndpoints(): number {
    return this.endpoints.filter(e => e.is_active).length;
  }

  /** Count of inactive endpoints. */
  get inactiveEndpoints(): number {
    return this.endpoints.filter(e => !e.is_active).length;
  }

  /**
   * Approximate average response time for demo purposes.
   * Returns 0 when there are no endpoints.
   */
  get averageResponseTime(): number {
    if (this.endpoints.length === 0) return 0;
    // This would come from monitoring results in a real implementation
    return Math.round(Math.random() * 200 + 100); // Mock data
  }

  /** Approximate uptime percentage for demo purposes. */
  get uptimePercentage(): number {
    // This would be calculated from monitoring results
    return Math.round(Math.random() * 20 + 80); // Mock data
  }

  /** Approximate alert count for demo purposes. */
  get alertsCount(): number {
    // This would come from alert system
    return Math.round(Math.random() * 5); // Mock data
  }

  /**
   * Triggers monitoring flow and informs the user.
   * Real implementation should delegate to a service or backend job trigger.
   */
  runMonitoring(): void {
    // This would trigger the Go monitoring service
    alert('Monitoring started! Check the console for real-time updates.');
  }
}
