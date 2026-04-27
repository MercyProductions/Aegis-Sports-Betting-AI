param(
    [string]$Configuration = "Release",
    [string]$Platform = "x64"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$exe = Join-Path $root "$Platform\$Configuration\AegisSportsBettingAI.exe"
$outDir = Join-Path $root "dist\screenshot-smoke"
$errors = New-Object System.Collections.Generic.List[string]

function Assert-Screen($condition, $message) {
    if (-not $condition) {
        $script:errors.Add($message)
    }
}

Assert-Screen (Test-Path $exe) "Release executable is missing. Build Release before screenshot smoke tests."
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

Add-Type -AssemblyName System.Drawing
Add-Type -ReferencedAssemblies "System.Drawing" @"
using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Runtime.InteropServices;

public static class AegisWindowCapture
{
    [StructLayout(LayoutKind.Sequential)]
    public struct RECT
    {
        public int Left;
        public int Top;
        public int Right;
        public int Bottom;
    }

    [DllImport("user32.dll")]
    public static extern bool GetWindowRect(IntPtr hWnd, out RECT rect);

    public static void Capture(IntPtr hWnd, string path)
    {
        RECT rect;
        if (!GetWindowRect(hWnd, out rect))
            throw new InvalidOperationException("Could not read window bounds.");
        int width = Math.Max(1, rect.Right - rect.Left);
        int height = Math.Max(1, rect.Bottom - rect.Top);
        using (Bitmap bmp = new Bitmap(width, height))
        using (Graphics g = Graphics.FromImage(bmp))
        {
            g.CopyFromScreen(rect.Left, rect.Top, 0, 0, new Size(width, height));
            bmp.Save(path, ImageFormat.Png);
        }
    }
}
"@

$views = @("dashboard", "health", "settings", "reports", "watchlist", "scenario")
foreach ($view in $views) {
    $path = Join-Path $outDir "$view.png"
    $process = Start-Process -FilePath $exe -ArgumentList "--screenshot-smoke --smoke-view=$view" -PassThru
    try {
        $handle = [IntPtr]::Zero
        for ($i = 0; $i -lt 60; $i++) {
            Start-Sleep -Milliseconds 150
            $process.Refresh()
            if ($process.MainWindowHandle -ne 0) {
                $handle = $process.MainWindowHandle
                break
            }
        }
        Assert-Screen ($handle -ne [IntPtr]::Zero) "Could not find app window for $view."
        if ($handle -ne [IntPtr]::Zero) {
            Start-Sleep -Milliseconds 900
            [AegisWindowCapture]::Capture($handle, $path)
            $file = Get-Item $path -ErrorAction SilentlyContinue
            Assert-Screen ($file -and $file.Length -gt 20000) "Screenshot for $view is missing or too small."
        }
    }
    finally {
        if ($process -and -not $process.HasExited) {
            Stop-Process -Id $process.Id -Force
            $process.WaitForExit()
        }
    }
}

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "UI screenshot smoke tests passed. Screenshots: $outDir"
