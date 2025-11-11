package logger

import (
	"context"
)

// Context keys for logger
type contextKey string

const (
	requestIDKey contextKey = "request_id"
	userIDKey    contextKey = "user_id"
	endpointKey  contextKey = "endpoint"
)

// WithRequestID adds a request ID to the context
func WithRequestID(ctx context.Context, requestID string) context.Context {
	return context.WithValue(ctx, requestIDKey, requestID)
}

// GetRequestID retrieves the request ID from context
func GetRequestID(ctx context.Context) string {
	id, ok := ctx.Value(requestIDKey).(string)
	if !ok {
		return ""
	}
	return id
}

// WithUserID adds a user ID to the context
func WithUserID(ctx context.Context, userID string) context.Context {
	return context.WithValue(ctx, userIDKey, userID)
}

// GetUserID retrieves the user ID from context
func GetUserID(ctx context.Context) string {
	id, ok := ctx.Value(userIDKey).(string)
	if !ok {
		return ""
	}
	return id
}

// WithEndpoint adds an endpoint to the context
func WithEndpoint(ctx context.Context, endpoint string) context.Context {
	return context.WithValue(ctx, endpointKey, endpoint)
}

// GetEndpoint retrieves the endpoint from context
func GetEndpoint(ctx context.Context) string {
	ep, ok := ctx.Value(endpointKey).(string)
	if !ok {
		return ""
	}
	return ep
}
