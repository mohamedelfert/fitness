output "media_bucket" {
  value       = aws_s3_bucket.media.id
  description = "S3 bucket for media & progress photos."
}

output "api_ecr_repo" {
  value       = aws_ecr_repository.api.repository_url
  description = "ECR repository URL for the API image."
}
