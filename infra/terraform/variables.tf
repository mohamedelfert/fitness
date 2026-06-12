variable "app_name" {
  type        = string
  default     = "fitness-os"
  description = "Project/name prefix for all resources."
}

variable "environment" {
  type        = string
  description = "Deployment environment: staging | production."

  validation {
    condition     = contains(["staging", "production"], var.environment)
    error_message = "environment must be 'staging' or 'production'."
  }
}

variable "aws_region" {
  type        = string
  default     = "me-central-1" # UAE — MENA-first data residency (A2). Bahrain = me-south-1.
  description = "Region. Pinned to a MENA region for residency."
}
