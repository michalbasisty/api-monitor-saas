package logger

import (
	"fmt"
	"log"
	"os"
	"time"
)

// Level represents log level
type Level int

const (
	LevelDebug Level = iota
	LevelInfo
	LevelWarn
	LevelError
	LevelFatal
)

var levelNames = map[Level]string{
	LevelDebug: "DEBUG",
	LevelInfo:  "INFO",
	LevelWarn:  "WARN",
	LevelError: "ERROR",
	LevelFatal: "FATAL",
}

// Logger provides structured logging with context
type Logger struct {
	level   Level
	fields  map[string]interface{}
	stdLog  *log.Logger
}

// New creates a new logger with default configuration
func New() *Logger {
	return &Logger{
		level:  LevelInfo,
		fields: make(map[string]interface{}),
		stdLog: log.New(os.Stdout, "", 0),
	}
}

// SetLevel sets the minimum log level
func (l *Logger) SetLevel(level Level) {
	l.level = level
}

// WithField adds a field to the logger context
func (l *Logger) WithField(key string, value interface{}) *Logger {
	newLogger := &Logger{
		level:  l.level,
		fields: make(map[string]interface{}),
		stdLog: l.stdLog,
	}

	// Copy existing fields
	for k, v := range l.fields {
		newLogger.fields[k] = v
	}

	newLogger.fields[key] = value
	return newLogger
}

// WithFields adds multiple fields to the logger context
func (l *Logger) WithFields(fields map[string]interface{}) *Logger {
	newLogger := &Logger{
		level:  l.level,
		fields: make(map[string]interface{}),
		stdLog: l.stdLog,
	}

	// Copy existing fields
	for k, v := range l.fields {
		newLogger.fields[k] = v
	}

	// Add new fields
	for k, v := range fields {
		newLogger.fields[k] = v
	}

	return newLogger
}

// Debug logs a debug message
func (l *Logger) Debug(msg string, args ...interface{}) {
	if l.level <= LevelDebug {
		l.log(LevelDebug, msg, args...)
	}
}

// Info logs an info message
func (l *Logger) Info(msg string, args ...interface{}) {
	if l.level <= LevelInfo {
		l.log(LevelInfo, msg, args...)
	}
}

// Warn logs a warning message
func (l *Logger) Warn(msg string, args ...interface{}) {
	if l.level <= LevelWarn {
		l.log(LevelWarn, msg, args...)
	}
}

// Error logs an error message
func (l *Logger) Error(msg string, args ...interface{}) {
	if l.level <= LevelError {
		l.log(LevelError, msg, args...)
	}
}

// Fatal logs a fatal message and exits
func (l *Logger) Fatal(msg string, args ...interface{}) {
	l.log(LevelFatal, msg, args...)
	os.Exit(1)
}

// Debugf formats and logs a debug message
func (l *Logger) Debugf(format string, args ...interface{}) {
	if l.level <= LevelDebug {
		l.logf(LevelDebug, format, args...)
	}
}

// Infof formats and logs an info message
func (l *Logger) Infof(format string, args ...interface{}) {
	if l.level <= LevelInfo {
		l.logf(LevelInfo, format, args...)
	}
}

// Warnf formats and logs a warning message
func (l *Logger) Warnf(format string, args ...interface{}) {
	if l.level <= LevelWarn {
		l.logf(LevelWarn, format, args...)
	}
}

// Errorf formats and logs an error message
func (l *Logger) Errorf(format string, args ...interface{}) {
	if l.level <= LevelError {
		l.logf(LevelError, format, args...)
	}
}

// Fatalf formats and logs a fatal message and exits
func (l *Logger) Fatalf(format string, args ...interface{}) {
	l.logf(LevelFatal, format, args...)
	os.Exit(1)
}

// log logs a message with fields
func (l *Logger) log(level Level, msg string, args ...interface{}) {
	if len(args) > 0 {
		msg = fmt.Sprintf(msg, args...)
	}

	output := l.formatMessage(level, msg)
	l.stdLog.Println(output)
}

// logf formats and logs a message
func (l *Logger) logf(level Level, format string, args ...interface{}) {
	msg := fmt.Sprintf(format, args...)
	output := l.formatMessage(level, msg)
	l.stdLog.Println(output)
}

// formatMessage formats the log message with timestamp and fields
func (l *Logger) formatMessage(level Level, msg string) string {
	timestamp := time.Now().Format("2006-01-02T15:04:05.000Z07:00")
	levelStr := levelNames[level]

	// Base message
	output := fmt.Sprintf("[%s] %s: %s", timestamp, levelStr, msg)

	// Add fields if any
	if len(l.fields) > 0 {
		output += " | "
		for key, value := range l.fields {
			output += fmt.Sprintf("%s=%v ", key, value)
		}
	}

	return output
}
