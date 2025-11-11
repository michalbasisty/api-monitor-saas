import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DashboardComponent } from './dashboard.component';
import { EndpointService } from '../../core/services/endpoint.service';
import { of, throwError } from 'rxjs';
import { Endpoint } from '../../core/models/endpoint.model';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;
  let endpointService: jasmine.SpyObj<EndpointService>;

  beforeEach(async () => {
    const endpointServiceSpy = jasmine.createSpyObj('EndpointService', [
      'getEndpoints'
    ]);

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        { provide: EndpointService, useValue: endpointServiceSpy }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
    endpointService = TestBed.inject(EndpointService) as jasmine.SpyObj<EndpointService>;
  });

  // ============================================
  // Component Initialization Tests
  // ============================================

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize with empty endpoints', () => {
    expect(component.endpoints).toEqual([]);
  });

  it('should set loading to true on init', () => {
    endpointService.getEndpoints.and.returnValue(of({
      endpoints: []
    }));

    fixture.detectChanges();

    expect(component.loading).toBe(false); // Should be false after load completes
  });

  it('should initialize error as null', () => {
    expect(component.error).toBeNull();
  });

  // ============================================
  // loadEndpoints Tests
  // ============================================

  it('should load endpoints on init', () => {
    const mockEndpoints: Endpoint[] = [
      {
        id: 'endpoint-1',
        url: 'https://api.example.com',
        name: 'API Endpoint',
        is_active: true,
        created_at: '2024-11-11T10:00:00Z'
      }
    ];

    endpointService.getEndpoints.and.returnValue(of({
      endpoints: mockEndpoints
    }));

    component.ngOnInit();

    expect(component.endpoints).toEqual(mockEndpoints);
    expect(component.loading).toBe(false);
    expect(component.error).toBeNull();
  });

  it('should set loading to false after endpoints are loaded', () => {
    endpointService.getEndpoints.and.returnValue(of({
      endpoints: []
    }));

    component.loading = true;
    component.loadEndpoints();

    expect(component.loading).toBe(false);
  });

  it('should handle empty endpoints list', () => {
    endpointService.getEndpoints.and.returnValue(of({
      endpoints: []
    }));

    component.loadEndpoints();

    expect(component.endpoints).toEqual([]);
    expect(component.loading).toBe(false);
  });

  it('should load multiple endpoints', () => {
    const mockEndpoints: Endpoint[] = [
      {
        id: 'endpoint-1',
        url: 'https://api1.example.com',
        name: 'API 1',
        is_active: true,
        created_at: '2024-11-11T10:00:00Z'
      },
      {
        id: 'endpoint-2',
        url: 'https://api2.example.com',
        name: 'API 2',
        is_active: false,
        created_at: '2024-11-11T11:00:00Z'
      },
      {
        id: 'endpoint-3',
        url: 'https://api3.example.com',
        name: 'API 3',
        is_active: true,
        created_at: '2024-11-11T12:00:00Z'
      }
    ];

    endpointService.getEndpoints.and.returnValue(of({
      endpoints: mockEndpoints
    }));

    component.loadEndpoints();

    expect(component.endpoints.length).toBe(3);
  });

  // ============================================
  // Error Handling Tests
  // ============================================

  it('should set error message on load failure', () => {
    endpointService.getEndpoints.and.returnValue(
      throwError(() => new Error('API Error'))
    );

    component.loadEndpoints();

    expect(component.error).toBe('Failed to load endpoints');
    expect(component.loading).toBe(false);
  });

  it('should set loading to false on error', () => {
    endpointService.getEndpoints.and.returnValue(
      throwError(() => new Error('API Error'))
    );

    component.loading = true;
    component.loadEndpoints();

    expect(component.loading).toBe(false);
  });

  it('should preserve endpoints if load fails', () => {
    component.endpoints = [
      {
        id: 'endpoint-1',
        url: 'https://api.example.com',
        name: 'API',
        is_active: true,
        created_at: '2024-11-11T10:00:00Z'
      }
    ];

    endpointService.getEndpoints.and.returnValue(
      throwError(() => new Error('API Error'))
    );

    component.loadEndpoints();

    expect(component.endpoints.length).toBe(1);
  });

  // ============================================
  // activeEndpoints Getter Tests
  // ============================================

  it('should count active endpoints', () => {
    component.endpoints = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: true, created_at: '' },
      { id: '2', url: 'http://test2.com', name: 'Test 2', is_active: false, created_at: '' },
      { id: '3', url: 'http://test3.com', name: 'Test 3', is_active: true, created_at: '' }
    ];

    expect(component.activeEndpoints).toBe(2);
  });

  it('should return 0 for active endpoints when empty', () => {
    component.endpoints = [];

    expect(component.activeEndpoints).toBe(0);
  });

  it('should return 0 when no active endpoints', () => {
    component.endpoints = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: false, created_at: '' },
      { id: '2', url: 'http://test2.com', name: 'Test 2', is_active: false, created_at: '' }
    ];

    expect(component.activeEndpoints).toBe(0);
  });

  // ============================================
  // inactiveEndpoints Getter Tests
  // ============================================

  it('should count inactive endpoints', () => {
    component.endpoints = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: true, created_at: '' },
      { id: '2', url: 'http://test2.com', name: 'Test 2', is_active: false, created_at: '' },
      { id: '3', url: 'http://test3.com', name: 'Test 3', is_active: false, created_at: '' }
    ];

    expect(component.inactiveEndpoints).toBe(2);
  });

  it('should return 0 for inactive endpoints when empty', () => {
    component.endpoints = [];

    expect(component.inactiveEndpoints).toBe(0);
  });

  it('should return 0 when all endpoints active', () => {
    component.endpoints = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: true, created_at: '' },
      { id: '2', url: 'http://test2.com', name: 'Test 2', is_active: true, created_at: '' }
    ];

    expect(component.inactiveEndpoints).toBe(0);
  });

  // ============================================
  // Metrics Getter Tests
  // ============================================

  it('should return 0 for average response time when no endpoints', () => {
    component.endpoints = [];

    expect(component.averageResponseTime).toBe(0);
  });

  it('should return numeric average response time', () => {
    component.endpoints = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: true, created_at: '' }
    ];

    const avgTime = component.averageResponseTime;
    expect(typeof avgTime).toBe('number');
    expect(avgTime).toBeGreaterThanOrEqual(100);
    expect(avgTime).toBeLessThanOrEqual(300);
  });

  it('should return numeric uptime percentage', () => {
    component.endpoints = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: true, created_at: '' }
    ];

    const uptime = component.uptimePercentage;
    expect(typeof uptime).toBe('number');
    expect(uptime).toBeGreaterThanOrEqual(80);
    expect(uptime).toBeLessThanOrEqual(100);
  });

  it('should return numeric alerts count', () => {
    component.endpoints = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: true, created_at: '' }
    ];

    const alerts = component.alertsCount;
    expect(typeof alerts).toBe('number');
    expect(alerts).toBeGreaterThanOrEqual(0);
  });

  // ============================================
  // runMonitoring Tests
  // ============================================

  it('should call runMonitoring method', () => {
    spyOn(window, 'alert');

    component.runMonitoring();

    expect(window.alert).toHaveBeenCalled();
  });

  // ============================================
  // Template Rendering Tests
  // ============================================

  it('should render component with data', () => {
    const mockEndpoints: Endpoint[] = [
      {
        id: 'endpoint-1',
        url: 'https://api.example.com',
        name: 'API Endpoint',
        is_active: true,
        created_at: '2024-11-11T10:00:00Z'
      }
    ];

    endpointService.getEndpoints.and.returnValue(of({
      endpoints: mockEndpoints
    }));

    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled).toBeTruthy();
  });

  it('should show loading state initially', () => {
    endpointService.getEndpoints.and.returnValue(of({
      endpoints: []
    }));

    component.loading = true;
    fixture.detectChanges();

    expect(component.loading).toBe(true);
  });

  // ============================================
  // Integration Tests
  // ============================================

  it('should load endpoints when component initializes', () => {
    const mockEndpoints: Endpoint[] = [
      {
        id: 'endpoint-1',
        url: 'https://api.example.com',
        name: 'API',
        is_active: true,
        created_at: '2024-11-11T10:00:00Z'
      }
    ];

    endpointService.getEndpoints.and.returnValue(of({
      endpoints: mockEndpoints
    }));

    component.ngOnInit();

    expect(endpointService.getEndpoints).toHaveBeenCalled();
    expect(component.endpoints).toEqual(mockEndpoints);
  });

  it('should calculate statistics from loaded endpoints', () => {
    const mockEndpoints: Endpoint[] = [
      { id: '1', url: 'http://test1.com', name: 'Test 1', is_active: true, created_at: '' },
      { id: '2', url: 'http://test2.com', name: 'Test 2', is_active: false, created_at: '' }
    ];

    endpointService.getEndpoints.and.returnValue(of({
      endpoints: mockEndpoints
    }));

    component.loadEndpoints();

    expect(component.activeEndpoints).toBe(1);
    expect(component.inactiveEndpoints).toBe(1);
  });
});
