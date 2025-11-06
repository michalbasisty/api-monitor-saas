import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { NavbarComponent } from '../../../shared/navbar/navbar.component';
import { EndpointService } from '../../../core/services/endpoint.service';
import { MonitoringService } from '../../../core/services/monitoring.service';
import { Endpoint } from '../../../core/models/endpoint.model';
import { MonitoringStats, MonitoringResult } from '../../../core/models/monitoring.model';

@Component({
  selector: 'app-endpoint-detail',
  standalone: true,
  imports: [CommonModule, RouterLink, NavbarComponent],
  templateUrl: './endpoint-detail.component.html',
  styleUrl: './endpoint-detail.component.css'
})
export class EndpointDetailComponent implements OnInit {
  endpoint: Endpoint | null = null;
  stats: MonitoringStats | null = null;
  results: MonitoringResult[] = [];
  loading = true;
  error: string | null = null;

  constructor(
    private route: ActivatedRoute,
    private endpointService: EndpointService,
    private monitoringService: MonitoringService
  ) {}

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.loadEndpoint(id);
      this.loadStats(id);
      this.loadResults(id);
    }
  }

  loadEndpoint(id: string): void {
    this.endpointService.getEndpoint(id).subscribe({
      next: (endpoint) => {
        this.endpoint = endpoint;
        this.loading = false;
      },
      error: (err) => {
        this.error = 'Failed to load endpoint';
        this.loading = false;
      }
    });
  }

  loadStats(id: string): void {
    this.monitoringService.getStats(id, 24).subscribe({
      next: (stats) => {
        this.stats = stats;
      },
      error: (err) => {
        console.error('Failed to load stats', err);
      }
    });
  }

  loadResults(id: string): void {
    this.monitoringService.getResults(id, 10).subscribe({
      next: (response) => {
        this.results = response.results;
      },
      error: (err) => {
        console.error('Failed to load results', err);
      }
    });
  }
}
