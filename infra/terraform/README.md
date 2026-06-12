# Infrastructure as Code (Terraform)

> **Status:** Baseline skeleton. **Nothing has been applied.** This establishes the IaC
> convention, remote-state layout, and region pinning. It targets **AWS** as a sensible
> default (MENA regions for the A2 data-residency requirement) — **confirm the cloud
> provider and account before `apply`.**

## What's here (foundational, low-risk)
- `versions.tf` — Terraform + AWS provider pins; S3 remote-state backend (commented until the state bucket exists).
- `providers.tf` — AWS provider with default tags.
- `variables.tf` — `app_name`, `environment` (staging|production), `aws_region` (default `me-central-1`).
- `main.tf` — encrypted private S3 media bucket + ECR repo for the API image.
- `outputs.tf`, `environments/{staging,production}.tfvars`.

## Deferred until provider/account confirmed (the real stack)
VPC + subnets · **RDS MySQL 8** (multi-AZ, region-pinned for residency) · **ElastiCache Redis** ·
compute (ECS Fargate or EKS) · CloudFront CDN · **WAF** · Secrets Manager/KMS · CloudWatch +
OpenTelemetry collector · automated backups. These map to `SYSTEM_ARCHITECTURE.md §8–§11`.

## Usage (once an account exists)
```bash
cd infra/terraform
terraform init                                   # after enabling the S3 backend in versions.tf
terraform plan  -var-file=environments/staging.tfvars
terraform apply -var-file=environments/production.tfvars
```

## Notes
- Region is pinned to a MENA region (`me-central-1` UAE; `me-south-1` Bahrain) for **data
  residency (A2)**. Enterprise tenants needing isolation get region-pinned dedicated DBs (ADR-008).
- Local development does **not** use this — see root `docker-compose.yml`.
- Terraform is not installed in the current build environment, so these files have not been
  `validate`d locally; run `terraform fmt -check && terraform validate` in CI/your machine.
