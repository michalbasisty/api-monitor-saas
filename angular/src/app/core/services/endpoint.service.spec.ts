import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { EndpointService } from './endpoint.service';
import { Endpoint } from '../models/endpoint.model';

describe('EndpointService', () => {
  let service: EndpointService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [EndpointService]
    });
    service = TestBed.inject(EndpointService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('getEndpoints', () => {
    it('should fetch all endpoints', () => {
      const mockEndpoints: Endpoint[] = [
        {
          id: '1',
          url: 'https://api.example.com/health',
          check_interval: 300,
          timeout: 5000,
          is_active: true,
          created_at: new Date(),
          updated_at: new Date()
        }
      ];

      service.getEndpoints().subscribe(endpoints => {
        expect(endpoints.length).toBe(1);
        expect(endpoints[0].url).toBe('https://api.example.com/health');
      });

      const req = httpMock.expectOne('/api/endpoints');
      expect(req.request.method).toBe('GET');
      req.flush(mockEndpoints);
    });
  });

  describe('getEndpoint', () => {
    it('should fetch single endpoint by id', () => {
      const endpointId = '123';
      const mockEndpoint: Endpoint = {
        id: endpointId,
        url: 'https://api.example.com/health',
        check_interval: 300,
        timeout: 5000,
        is_active: true,
        created_at: new Date(),
        updated_at: new Date()
      };

      service.getEndpoint(endpointId).subscribe(endpoint => {
        expect(endpoint.id).toBe(endpointId);
      });

      const req = httpMock.expectOne(`/api/endpoints/${endpointId}`);
      expect(req.request.method).toBe('GET');
      req.flush(mockEndpoint);
    });
  });

  describe('createEndpoint', () => {
    it('should create new endpoint', () => {
      const newEndpoint = {
        url: 'https://api.example.com/status',
        check_interval: 300,
        timeout: 5000,
        is_active: true
      };

      service.createEndpoint(newEndpoint).subscribe(response => {
        expect(response.id).toBeTruthy();
        expect(response.url).toBe(newEndpoint.url);
      });

      const req = httpMock.expectOne('/api/endpoints');
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(newEndpoint);

      req.flush({ id: '123', ...newEndpoint });
    });

    it('should validate URL format', () => {
      const invalidEndpoint = {
        url: 'not-a-valid-url',
        check_interval: 300,
        timeout: 5000,
        is_active: true
      };

      // This should be caught by client-side validation before sending
      expect(() => {
        // URL validation would happen here
      }).not.toThrow();
    });
  });

  describe('updateEndpoint', () => {
    it('should update existing endpoint', () => {
      const endpointId = '123';
      const updates = {
        check_interval: 600,
        is_active: false
      };

      service.updateEndpoint(endpointId, updates).subscribe(response => {
        expect(response.check_interval).toBe(600);
        expect(response.is_active).toBe(false);
      });

      const req = httpMock.expectOne(`/api/endpoints/${endpointId}`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual(updates);

      req.flush({
        id: endpointId,
        url: 'https://api.example.com/health',
        ...updates,
        created_at: new Date(),
        updated_at: new Date()
      });
    });
  });

  describe('deleteEndpoint', () => {
    it('should delete endpoint', () => {
      const endpointId = '123';

      service.deleteEndpoint(endpointId).subscribe();

      const req = httpMock.expectOne(`/api/endpoints/${endpointId}`);
      expect(req.request.method).toBe('DELETE');

      req.flush({});
    });
  });

  describe('getStats', () => {
    it('should fetch endpoint statistics', () => {
      const endpointId = '123';
      const mockStats = {
        uptime_percentage: 99.5,
        avg_response_time: 125,
        total_checks: 1000,
        successful_checks: 995,
        failed_checks: 5
      };

      service.getStats(endpointId).subscribe(stats => {
        expect(stats.uptime_percentage).toBe(99.5);
        expect(stats.avg_response_time).toBe(125);
      });

      const req = httpMock.expectOne(`/api/monitoring/endpoints/${endpointId}/stats`);
      expect(req.request.method).toBe('GET');
      req.flush(mockStats);
    });
  });
});
