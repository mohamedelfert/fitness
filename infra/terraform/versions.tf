terraform {
  required_version = ">= 1.6"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  # Remote state. Uncomment and create the bucket/lock table once the cloud
  # account exists (region-pinned for data residency — A2).
  # backend "s3" {
  #   bucket         = "fitness-os-tfstate"
  #   key            = "env/terraform.tfstate"
  #   region         = "me-central-1"
  #   dynamodb_table = "fitness-os-tflock"
  #   encrypt        = true
  # }
}
