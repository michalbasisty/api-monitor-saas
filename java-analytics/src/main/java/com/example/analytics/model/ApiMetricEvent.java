package com.example.analytics.model;

import lombok.Data;
import lombok.NoArgsConstructor;
import com.fasterxml.jackson.annotation.JsonProperty;
import java.time.Instant;

@Data
@NoArgsConstructor
public class ApiMetricEvent {
    
    @JsonProperty("endpoint_id")
    private String endpointId;
    
    @JsonProperty("response_time")
    private Double responseTime;
    
    @JsonProperty("status_code")
    private Integer statusCode;
    
    @JsonProperty("timestamp")
    private Instant timestamp;
    
    @JsonProperty("method")
    private String method;
    
    @JsonProperty("path")
    private String path;
}