package database

import (
	"os"
	"testing"
)

func TestGetEnv(t *testing.T) {
	tests := []struct {
		name         string
		key          string
		defaultValue string
		envValue     string
		expected     string
	}{
		{
			name:         "returns env var when set",
			key:          "TEST_VAR",
			defaultValue: "default",
			envValue:     "custom",
			expected:     "custom",
		},
		{
			name:         "returns default when env var not set",
			key:          "NONEXISTENT_VAR",
			defaultValue: "default",
			envValue:     "",
			expected:     "default",
		},
		{
			name:         "returns empty string when both empty",
			key:          "EMPTY_VAR",
			defaultValue: "",
			envValue:     "",
			expected:     "",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Setup
			if tt.envValue != "" {
				os.Setenv(tt.key, tt.envValue)
				defer os.Unsetenv(tt.key)
			} else {
				os.Unsetenv(tt.key)
			}

			// Execute
			result := getEnv(tt.key, tt.defaultValue)

			// Assert
			if result != tt.expected {
				t.Errorf("getEnv(%q, %q) = %q, want %q", tt.key, tt.defaultValue, result, tt.expected)
			}
		})
	}
}

func TestNewDBEnvVariables(t *testing.T) {
	tests := []struct {
		name     string
		host     string
		port     string
		user     string
		password string
		dbname   string
		wantErr  bool
	}{
		{
			name:     "invalid host",
			host:     "invalid-host",
			port:     "5432",
			user:     "appuser",
			password: "password",
			dbname:   "apimon",
			wantErr:  true,
		},
		{
			name:     "invalid port",
			host:     "localhost",
			port:     "99999",
			user:     "appuser",
			password: "password",
			dbname:   "apimon",
			wantErr:  true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Setup env vars
			os.Setenv("POSTGRES_HOST", tt.host)
			os.Setenv("POSTGRES_PORT", tt.port)
			os.Setenv("POSTGRES_USER", tt.user)
			os.Setenv("POSTGRES_PASSWORD", tt.password)
			os.Setenv("POSTGRES_DB", tt.dbname)
			defer func() {
				os.Unsetenv("POSTGRES_HOST")
				os.Unsetenv("POSTGRES_PORT")
				os.Unsetenv("POSTGRES_USER")
				os.Unsetenv("POSTGRES_PASSWORD")
				os.Unsetenv("POSTGRES_DB")
			}()

			// Execute
			_, err := NewDB()

			// Assert
			if (err != nil) != tt.wantErr {
				t.Errorf("NewDB() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func TestDBClose(t *testing.T) {
	t.Run("closes without error when db is nil", func(t *testing.T) {
		db := &DB{
			Postgres: nil,
			Redis:    nil,
		}

		err := db.Close()
		if err != nil {
			t.Errorf("Close() = %v, want nil", err)
		}
	})
}
