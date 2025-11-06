package com.example.analytics.service;

import com.example.analytics.model.ApiMetricEvent;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.nio.charset.StandardCharsets;
import jakarta.annotation.PostConstruct;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.data.redis.connection.RedisConnection;
import org.springframework.data.redis.connection.RedisConnectionFactory;
import org.springframework.data.redis.connection.stream.Consumer;
import org.springframework.data.redis.connection.stream.MapRecord;
import org.springframework.data.redis.connection.stream.ReadOffset;
import org.springframework.data.redis.connection.stream.StreamOffset;
import org.springframework.data.redis.core.RedisTemplate;
import org.springframework.data.redis.stream.StreamMessageListenerContainer;
import org.springframework.stereotype.Service;

import java.time.Duration;
import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

@Slf4j
@Service
@RequiredArgsConstructor
public class MetricsProcessor {
    
    private final StreamMessageListenerContainer<String, MapRecord<String, String, String>> listenerContainer;
    private final RedisTemplate<String, Object> redisTemplate;
    private final RedisConnectionFactory connectionFactory;
    private final ObjectMapper objectMapper;
    
    private static final String STREAM_KEY = "api-metrics";
    private static final String STATS_PREFIX = "stats:";
    private static final String CONSUMER_GROUP = "analytics-group";
    private static final String CONSUMER_NAME = "analytics-instance-1";
    private static final Duration WINDOW_SIZE = Duration.ofMinutes(5);
    
    // In-memory cache for quick stats calculation
    private final Map<String, MetricWindow> metricWindows = new ConcurrentHashMap<>();
    
    @PostConstruct
    public void init() {
        try {
            // Ensure consumer group exists; start from beginning if newly created
            try {
                redisTemplate.opsForStream().createGroup(STREAM_KEY, ReadOffset.from("0-0"), CONSUMER_GROUP);
                log.info("Created consumer group '{}' for stream {}", CONSUMER_GROUP, STREAM_KEY);
            } catch (Exception e) {
                // If the group already exists, ignore
                log.debug("Consumer group may already exist: {}", e.getMessage());
            }

            Consumer consumer = Consumer.from(CONSUMER_GROUP, CONSUMER_NAME);

            listenerContainer.receive(
                consumer,
                StreamOffset.create(STREAM_KEY, ReadOffset.lastConsumed()),
                this::processMetric
            );

            listenerContainer.start();
            log.info("Started listening for API metrics on Redis stream '{}' as consumer '{}'", STREAM_KEY, consumer.getName());
        } catch (Exception ex) {
            log.error("Failed to start MetricsProcessor listener: {}", ex.getMessage(), ex);
        }
    }
    
    private void processMetric(MapRecord<String, String, String> record) {
        processMetricMap(record.getId().getValue(), record.getValue());
    }

    private void processMetricMap(String recordId, Map<String, String> value) {
        try {
            if (!value.containsKey("endpoint_id")) {
                log.debug("Skipping non-metric record: {}", value);
                return;
            }

            // Convert the record to ApiMetricEvent
            ApiMetricEvent event = convertToMetricEvent(value);
            if (event == null || event.getEndpointId() == null) {
                log.debug("Converted metric is missing endpoint_id, skipping: {}", value);
                return;
            }
            log.debug("Processing metric event: {}", event);

            // Update real-time statistics
            updateMetricWindow(event);

            // Store the raw event data
            storeMetricEvent(event);

            // Check for anomalies
            checkForAnomalies(event);

            // Acknowledge the message so it is removed from the pending entries list
            try {
                Long acked = redisTemplate.opsForStream().acknowledge(STREAM_KEY, CONSUMER_GROUP, recordId);
                log.debug("Acknowledged record {} (acked={})", recordId, acked);
            } catch (Exception ackEx) {
                log.warn("Failed to acknowledge record {}: {}", recordId, ackEx.getMessage());
            }

        } catch (Exception e) {
            log.error("Error processing metric: {}", e.getMessage(), e);
        }
    }
    
    private ApiMetricEvent convertToMetricEvent(Map<String, String> value) {
        try {
            ApiMetricEvent event = new ApiMetricEvent();
            event.setEndpointId(value.get("endpoint_id"));

            String rt = value.get("response_time");
            if (rt != null) {
                event.setResponseTime(Double.valueOf(rt));
            }

            String sc = value.get("status_code");
            if (sc != null) {
                event.setStatusCode(Integer.valueOf(sc));
            }

            String ts = value.get("timestamp");
            if (ts != null) {
                event.setTimestamp(Instant.parse(ts));
            }

            event.setMethod(value.get("method"));
            event.setPath(value.get("path"));

            return event;
        } catch (Exception e) {
            log.error("Error converting record to ApiMetricEvent: {}", e.getMessage());
            throw new RuntimeException("Failed to convert metric record", e);
        }
    }

    // Helper to convert various runtime key/value shapes to String
    private String convertObjectToString(Object obj) {
        if (obj == null) return null;
        try {
            if (obj instanceof String) return (String) obj;
            if (obj instanceof byte[]) return new String((byte[]) obj, StandardCharsets.UTF_8);
            if (obj instanceof java.lang.Number) return String.valueOf(obj);
            if (obj instanceof CharSequence) return obj.toString();
            if (obj instanceof Character) return obj.toString();
            // Fallback: attempt to JSON-serialize/deserialize simple shapes
            try {
                return new String(objectMapper.writeValueAsBytes(obj), StandardCharsets.UTF_8);
            } catch (Exception ignore) {
                return obj.toString();
            }
        } catch (Exception e) {
            return null;
        }
    }

    @Value("${analytics.reclaimer.min-idle-ms:1000}")
    private long minIdleMs;

    // Periodically scan the consumer group's pending entries and claim items idle longer than minIdleMs
    @Scheduled(fixedDelayString = "${analytics.reclaimer.interval-ms:5000}")
    public void reclaimPending() {
        try {
            log.info("Starting pending entries reclaim cycle...");
            // Use the high-level opsForStream().pending to get pending information.
            // Different Spring Data Redis versions return either PendingMessages or PendingMessagesSummary.
            // Use an Object and handle both shapes at runtime to avoid compile-time type incompatibilities.
            Object pendingObj = redisTemplate.opsForStream().pending(STREAM_KEY, CONSUMER_GROUP);
            if (pendingObj == null) {
                log.debug("No pending entries found");
                return;
            }
            log.debug("Retrieved pending entries info: {}", pendingObj);

            // Get detailed pending entries directly using XPENDING IDLE
            try (RedisConnection connection = redisTemplate.getConnectionFactory().getConnection()) {
                byte[] streamKey = STREAM_KEY.getBytes(StandardCharsets.UTF_8);
                // Get pending messages with XPENDING using the String version
                // Get the raw pending info first to see if there are any messages
                org.springframework.data.redis.connection.stream.PendingMessagesSummary summary = 
                    connection.streamCommands().xPending(streamKey, CONSUMER_GROUP);

                if (summary == null || summary.getTotalPendingMessages() == 0) {
                    log.debug("No pending entries found with idle time > {}ms", minIdleMs);
                    return;
                }

                // Get all pending messages
                List<org.springframework.data.redis.connection.stream.PendingMessage> pendingMessages =
                    connection.streamCommands().xPendingMessages(streamKey, CONSUMER_GROUP, org.springframework.data.domain.Range.unbounded(), (int) summary.getTotalPendingMessages());

                if (pendingMessages == null || pendingMessages.isEmpty()) {
                    log.debug("No pending messages to process");
                    return;
                }

                log.info("Found {} pending entries", pendingMessages.size());

                for (org.springframework.data.redis.connection.stream.PendingMessage pm : pendingMessages) {
                    String id = pm.getIdAsString();
                    log.debug("Attempting to claim entry {}", id);

                    // Convert String ID to RecordId
                    org.springframework.data.redis.connection.stream.RecordId recordId = 
                        org.springframework.data.redis.connection.stream.RecordId.of(id);

                    // Claim the message with proper generics
                    @SuppressWarnings("unchecked")
                    List<MapRecord<String, Object, Object>> claimedRaw = 
                        (List<MapRecord<String, Object, Object>>) redisTemplate.opsForStream()
                            .claim(STREAM_KEY, CONSUMER_GROUP, CONSUMER_NAME, Duration.ofMillis(minIdleMs), recordId);
                            
                    // Convert to correct types - MapRecord<String, String, String>
                    List<MapRecord<String, String, String>> claimed = claimedRaw.stream()
                        .map(record -> {
                            Map<String, String> convertedValue = record.getValue().entrySet().stream()
                                .collect(java.util.stream.Collectors.toMap(
                                    e -> convertObjectToString(e.getKey()),
                                    e -> convertObjectToString(e.getValue())
                                ));
                            return MapRecord.<String, String, String>create(record.getStream(), convertedValue);
                        })
                        .collect(java.util.stream.Collectors.toList());

                    if (claimed == null || claimed.isEmpty()) {
                        log.debug("Failed to claim entry {}", id);
                        continue;
                    }

                    // Process each claimed message
                    for (org.springframework.data.redis.connection.stream.MapRecord<String, String, String> record : claimed) {
                        try {
                            processMetricMap(record.getId().getValue(), record.getValue());
                        } catch (Exception e) {
                            log.error("Error processing claimed entry {}: {}", id, e.getMessage(), e);
                        }
                    }
                }
            } catch (Exception e) {
                log.error("Error claiming pending entries: {}", e.getMessage(), e);
            }
        } catch (Exception e) {
            log.error("Error during pending reclaim: {}", e.getMessage(), e);
        }
    }
    
    private void updateMetricWindow(ApiMetricEvent event) {
        String key = event.getEndpointId();
        MetricWindow window = metricWindows.computeIfAbsent(key, k -> new MetricWindow());
        window.addMetric(event);
        
        // Store aggregated stats in Redis
        String statsKey = STATS_PREFIX + key;
        redisTemplate.opsForHash().put(statsKey, "avg_response_time", window.getAverageResponseTime());
        redisTemplate.opsForHash().put(statsKey, "error_rate", window.getErrorRate());
        redisTemplate.opsForHash().put(statsKey, "request_count", window.getRequestCount());
    }
    
    private void storeMetricEvent(ApiMetricEvent event) {
        String timeSeriesKey = String.format("timeseries:%s:%s", 
            event.getEndpointId(), 
            event.getTimestamp().toString());
            
        redisTemplate.opsForValue().set(timeSeriesKey, event);
        redisTemplate.expire(timeSeriesKey, Duration.ofDays(7)); // TTL for raw data
    }
    
    private void checkForAnomalies(ApiMetricEvent event) {
        MetricWindow window = metricWindows.get(event.getEndpointId());
        if (window == null) return;
        
        double avgResponseTime = window.getAverageResponseTime();
        double threshold = avgResponseTime * 2; // Alert if response time is 2x the average
        
        if (event.getResponseTime() > threshold) {
            log.warn("Anomaly detected for endpoint {}: Response time {} ms exceeds threshold {} ms",
                event.getEndpointId(), event.getResponseTime(), threshold);
                
            // TODO: Implement proper alerting mechanism
        }
    }
    
    private static class MetricWindow {
        private final ConcurrentHashMap<Instant, ApiMetricEvent> events = new ConcurrentHashMap<>();
        
        public void addMetric(ApiMetricEvent event) {
            // Remove old events
            Instant cutoff = Instant.now().minus(WINDOW_SIZE);
            events.entrySet().removeIf(entry -> entry.getKey().isBefore(cutoff));
            
            // Add new event
            events.put(event.getTimestamp(), event);
        }
        
        public double getAverageResponseTime() {
            return events.values().stream()
                .mapToDouble(ApiMetricEvent::getResponseTime)
                .average()
                .orElse(0.0);
        }
        
        public double getErrorRate() {
            long errorCount = events.values().stream()
                .filter(e -> e.getStatusCode() >= 500)
                .count();
            return events.size() > 0 ? (double) errorCount / events.size() : 0.0;
        }
        
        public int getRequestCount() {
            return events.size();
        }
    }
}