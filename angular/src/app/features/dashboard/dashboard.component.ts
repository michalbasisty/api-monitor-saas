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
export class DashboardComponent implements OnInit {
  endpoints: Endpoint[] = [];
  loading = true;
  error: string | null = null;

  constructor(private endpointService: EndpointService) {}

  ngOnInit(): void {
    this.loadEndpoints();
  }

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

  get activeEndpoints(): number {
    return this.endpoints.filter(e => e.is_active).length;
  }

  get inactiveEndpoints(): number {
    return this.endpoints.filter(e => !e.is_active).length;
  }

  get averageResponseTime(): number {
    if (this.endpoints.length === 0) return 0;
    // This would come from monitoring results in a real implementation
    return Math.round(Math.random() * 200 + 100); // Mock data
  }

  get uptimePercentage(): number {
    // This would be calculated from monitoring results
    return Math.round(Math.random() * 20 + 80); // Mock data
  }

  get alertsCount(): number {
    // This would come from alert system
    return Math.round(Math.random() * 5); // Mock data
  }

  runMonitoring(): void {
    // This would trigger the Go monitoring service
    alert('Monitoring started! Check the console for real-time updates.');
    console.log('Monitoring triggered...');
  }
}
