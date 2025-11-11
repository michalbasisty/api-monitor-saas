import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { MonitoringService } from './monitoring.service';
import { environment } from '../../../environments/environment';
import { MonitoringResult, MonitoringStats, MonitoringTimeline } from '../models/monitoring.model';

describe('MonitoringService', () => {
  let service: MonitoringService;
  let httpMock: HttpTestingController;
  const apiUrl = `${environment.apiUrl}/monitoring`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [MonitoringService]
    });
    service = TestBed.inject(MonitoringService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  // ============================================
  // getResults Tests
  // ============================================

  it('should fetch monitoring results for endpoint', (done) => {
    const endpointId = 'endpoint-123';
    const limit = 50;
    const mockResults = {
      endpoint_id: endpointId,
      total: 2,
      results: [
        {
          id: 'result-1',
          status_code: 200,
          response_time: 150,
          checked_at: '2024-11-11T10:00:00Z',
          is_success: true
        },
        {
          id: 'result-2',
          status_code: 200,
          response_time: 175,
          checked_at: '2024-11-11T10:05:00Z',
          is_success: true
        }
      ]
    };

    service.getResults(endpointId, limit).subscribe(data => {
      expect(data.endpoint_id).toBe(endpointId);
      expect(data.total).toBe(2);
      expect(data.results.length).toBe(2);
      expect(data.results[0].status_code).toBe(200);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/results?limit=${limit}`);
    expect(req.request.method).toBe('GET');
    req.flush(mockResults);
  });

  it('should use default limit of 100 if not provided', (done) => {
    const endpointId = 'endpoint-456';
    const mockResults = {
      endpoint_id: endpointId,
      total: 1,
      results: []
    };

    service.getResults(endpointId).subscribe(() => {
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/results?limit=100`);
    expect(req.request.method).toBe('GET');
    req.flush(mockResults);
  });

  it('should handle empty results', (done) => {
    const endpointId = 'endpoint-789';
    const mockResults = {
      endpoint_id: endpointId,
      total: 0,
      results: []
    };

    service.getResults(endpointId).subscribe(data => {
      expect(data.results.length).toBe(0);
      expect(data.total).toBe(0);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/results?limit=100`);
    req.flush(mockResults);
  });

  it('should include failed monitoring results', (done) => {
    const endpointId = 'endpoint-fail';
    const mockResults = {
      endpoint_id: endpointId,
      total: 1,
      results: [
        {
          id: 'result-fail',
          status_code: 500,
          response_time: 5000,
          checked_at: '2024-11-11T10:00:00Z',
          is_success: false,
          error: 'Server Error'
        }
      ]
    };

    service.getResults(endpointId).subscribe(data => {
      expect(data.results[0].is_success).toBe(false);
      expect(data.results[0].error).toBe('Server Error');
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/results?limit=100`);
    req.flush(mockResults);
  });

  // ============================================
  // getStats Tests
  // ============================================

  it('should fetch monitoring stats for endpoint', (done) => {
    const endpointId = 'endpoint-123';
    const mockStats: MonitoringStats = {
      endpoint_id: endpointId,
      uptime_percentage: 99.5,
      average_response_time: 156,
      total_checks: 100,
      failed_checks: 1,
      check_interval_seconds: 300
    };

    service.getStats(endpointId).subscribe(data => {
      expect(data.endpoint_id).toBe(endpointId);
      expect(data.uptime_percentage).toBe(99.5);
      expect(data.average_response_time).toBe(156);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/stats?hours=24`);
    expect(req.request.method).toBe('GET');
    req.flush(mockStats);
  });

  it('should support custom hours parameter', (done) => {
    const endpointId = 'endpoint-456';
    const hours = 7;
    const mockStats: MonitoringStats = {
      endpoint_id: endpointId,
      uptime_percentage: 99.2,
      average_response_time: 200,
      total_checks: 200,
      failed_checks: 2,
      check_interval_seconds: 300
    };

    service.getStats(endpointId, hours).subscribe(data => {
      expect(data.average_response_time).toBe(200);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/stats?hours=${hours}`);
    req.flush(mockStats);
  });

  it('should handle perfect uptime', (done) => {
    const endpointId = 'endpoint-perfect';
    const mockStats: MonitoringStats = {
      endpoint_id: endpointId,
      uptime_percentage: 100,
      average_response_time: 150,
      total_checks: 100,
      failed_checks: 0,
      check_interval_seconds: 300
    };

    service.getStats(endpointId).subscribe(data => {
      expect(data.uptime_percentage).toBe(100);
      expect(data.failed_checks).toBe(0);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/stats?hours=24`);
    req.flush(mockStats);
  });

  // ============================================
  // getTimeline Tests
  // ============================================

  it('should fetch monitoring timeline for endpoint', (done) => {
    const endpointId = 'endpoint-123';
    const mockTimeline: MonitoringTimeline = {
      endpoint_id: endpointId,
      data_points: [
        {
          timestamp: '2024-11-11T10:00:00Z',
          status_code: 200,
          response_time: 150,
          is_success: true
        },
        {
          timestamp: '2024-11-11T10:05:00Z',
          status_code: 200,
          response_time: 165,
          is_success: true
        }
      ]
    };

    service.getTimeline(endpointId).subscribe(data => {
      expect(data.endpoint_id).toBe(endpointId);
      expect(data.data_points.length).toBe(2);
      expect(data.data_points[0].response_time).toBe(150);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/timeline?hours=24`);
    expect(req.request.method).toBe('GET');
    req.flush(mockTimeline);
  });

  it('should support custom hours for timeline', (done) => {
    const endpointId = 'endpoint-456';
    const hours = 7;
    const mockTimeline: MonitoringTimeline = {
      endpoint_id: endpointId,
      data_points: []
    };

    service.getTimeline(endpointId, hours).subscribe(() => {
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/timeline?hours=${hours}`);
    req.flush(mockTimeline);
  });

  it('should handle timeline with failures', (done) => {
    const endpointId = 'endpoint-failures';
    const mockTimeline: MonitoringTimeline = {
      endpoint_id: endpointId,
      data_points: [
        {
          timestamp: '2024-11-11T10:00:00Z',
          status_code: 200,
          response_time: 150,
          is_success: true
        },
        {
          timestamp: '2024-11-11T10:05:00Z',
          status_code: 500,
          response_time: 5000,
          is_success: false
        },
        {
          timestamp: '2024-11-11T10:10:00Z',
          status_code: 200,
          response_time: 160,
          is_success: true
        }
      ]
    };

    service.getTimeline(endpointId).subscribe(data => {
      const failures = data.data_points.filter(p => !p.is_success);
      expect(failures.length).toBe(1);
      expect(failures[0].status_code).toBe(500);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/timeline?hours=24`);
    req.flush(mockTimeline);
  });

  // ============================================
  // Error Handling Tests
  // ============================================

  it('should handle error when fetching results', (done) => {
    const endpointId = 'endpoint-error';

    service.getResults(endpointId).subscribe(
      () => fail('should not succeed'),
      (error) => {
        expect(error.status).toBe(500);
        done();
      }
    );

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/results?limit=100`);
    req.flush('Server error', { status: 500, statusText: 'Internal Server Error' });
  });

  it('should handle 404 not found', (done) => {
    const endpointId = 'endpoint-notfound';

    service.getStats(endpointId).subscribe(
      () => fail('should not succeed'),
      (error) => {
        expect(error.status).toBe(404);
        done();
      }
    );

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/stats?hours=24`);
    req.flush('Not found', { status: 404, statusText: 'Not Found' });
  });

  it('should handle unauthorized access', (done) => {
    const endpointId = 'endpoint-auth';

    service.getTimeline(endpointId).subscribe(
      () => fail('should not succeed'),
      (error) => {
        expect(error.status).toBe(401);
        done();
      }
    );

    const req = httpMock.expectOne(`${apiUrl}/endpoints/${endpointId}/timeline?hours=24`);
    req.flush('Unauthorized', { status: 401, statusText: 'Unauthorized' });
  });
});
