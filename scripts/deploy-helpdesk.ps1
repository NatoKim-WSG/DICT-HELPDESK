param(
    [string]$Host = "redmine.siphosted.com",
    [string]$User = "admin",
    [string]$Branch = "main",
    [string]$RemotePath = "/opt/helpdesk",
    [switch]$SkipPush
)

$ErrorActionPreference = "Stop"

if (-not $SkipPush) {
    git push origin $Branch
}

$remoteCommand = @"
set -euo pipefail
cd '$RemotePath'
bash './scripts/deploy-helpdesk-remote.sh' '$Branch'
"@

ssh "$User@$Host" $remoteCommand
