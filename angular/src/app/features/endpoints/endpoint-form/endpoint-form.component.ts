import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { NavbarComponent } from '../../../shared/navbar/navbar.component';
import { EndpointService } from '../../../core/services/endpoint.service';

@Component({
  selector: 'app-endpoint-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, NavbarComponent],
  templateUrl: './endpoint-form.component.html',
  styleUrl: './endpoint-form.component.css'
})
export class EndpointFormComponent implements OnInit {
  endpointForm: FormGroup;
  error: string | null = null;
  loading = false;
  isEditMode = false;
  endpointId: string | null = null;

  constructor(
    private fb: FormBuilder,
    private endpointService: EndpointService,
    private router: Router,
    private route: ActivatedRoute
  ) {
    this.endpointForm = this.fb.group({
      url: ['', [Validators.required]],
      check_interval: [300, [Validators.required, Validators.min(60)]],
      timeout: [5000, [Validators.required, Validators.min(100), Validators.max(30000)]],
      is_active: [true]
    });
  }

  ngOnInit(): void {
    this.endpointId = this.route.snapshot.paramMap.get('id');
    if (this.endpointId) {
      this.isEditMode = true;
      this.loadEndpoint();
    }
  }

  loadEndpoint(): void {
    if (!this.endpointId) return;

    this.endpointService.getEndpoint(this.endpointId).subscribe({
      next: (endpoint) => {
        this.endpointForm.patchValue({
          url: endpoint.url,
          check_interval: endpoint.check_interval,
          timeout: endpoint.timeout,
          is_active: endpoint.is_active
        });
      },
      error: (err) => {
        this.error = 'Failed to load endpoint';
      }
    });
  }

  onSubmit(): void {
    if (this.endpointForm.invalid) {
      return;
    }

    this.loading = true;
    this.error = null;

    const observable = this.isEditMode && this.endpointId
      ? this.endpointService.updateEndpoint(this.endpointId, this.endpointForm.value)
      : this.endpointService.createEndpoint(this.endpointForm.value);

    observable.subscribe({
      next: () => {
        this.router.navigate(['/endpoints']);
      },
      error: (err) => {
        this.error = err.error?.message || 'Failed to save endpoint';
        this.loading = false;
      }
    });
  }

  cancel(): void {
    this.router.navigate(['/endpoints']);
  }
}
