param(
    [string]$Host = "redmine.siphosted.com",
    [string]$User = "admin",
    [string]$Branch = "main",
    [switch]$SkipPush
)

$ErrorActionPreference = "Stop"
$RemotePath = "/opt/helpdesk"

if (-not $SkipPush) {
    git push origin $Branch
}

$remoteCommand = @"
set -euo pipefail
cd '$RemotePath'
bash './scripts/deploy-helpdesk-remote.sh' '$Branch'
"@

ssh "$User@$Host" $remoteCommand
