import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { NavbarComponent } from '../../../shared/navbar/navbar.component';
import { EndpointService } from '../../../core/services/endpoint.service';
import { Endpoint } from '../../../core/models/endpoint.model';

@Component({
  selector: 'app-endpoint-list',
  standalone: true,
  imports: [CommonModule, RouterLink, NavbarComponent],
  templateUrl: './endpoint-list.component.html',
  styleUrl: './endpoint-list.component.css'
})
export class EndpointListComponent implements OnInit {
  endpoints: Endpoint[] = [];
  loading = true;
  error: string | null = null;

  constructor(private endpointService: EndpointService) {}

  ngOnInit(): void {
    this.loadEndpoints();
  }

  loadEndpoints(): void {
    this.loading = true;
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

  deleteEndpoint(id: string): void {
    if (!confirm('Are you sure you want to delete this endpoint?')) {
      return;
    }

    this.endpointService.deleteEndpoint(id).subscribe({
      next: () => {
        this.loadEndpoints();
      },
      error: (err) => {
        alert('Failed to delete endpoint');
      }
    });
  }

  getHeadersCount(headers: any): number {
    if (!headers) return 0;
    return Object.keys(headers).length;
  }
}
