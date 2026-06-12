# ---------------------------------------------------------------------------
# Foundational, low-risk resources only. The larger stack (VPC, RDS MySQL 8,
# ElastiCache Redis, ECS/EKS, CloudFront, WAF) is intentionally deferred until
# the cloud account & provider are confirmed — see README.md.
# ---------------------------------------------------------------------------

locals {
  prefix = "${var.app_name}-${var.environment}"
}

# Object storage for media & progress photos (encrypted, private, versioned).
# Matches DATABASE_DESIGN.md (S3-compatible, signed-URL access, extra-protected).
resource "aws_s3_bucket" "media" {
  bucket = "${local.prefix}-media"
}

resource "aws_s3_bucket_public_access_block" "media" {
  bucket                  = aws_s3_bucket.media.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_server_side_encryption_configuration" "media" {
  bucket = aws_s3_bucket.media.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "aws:kms"
    }
    bucket_key_enabled = true
  }
}

resource "aws_s3_bucket_versioning" "media" {
  bucket = aws_s3_bucket.media.id

  versioning_configuration {
    status = "Enabled"
  }
}

# Container registry for the API image (built in CI, deployed to compute).
resource "aws_ecr_repository" "api" {
  name                 = "${var.app_name}/api"
  image_tag_mutability = "IMMUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }
}
