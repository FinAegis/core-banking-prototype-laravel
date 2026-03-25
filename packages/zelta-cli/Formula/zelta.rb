# Homebrew formula for Zelta CLI
# Install: brew install zelta-app/tap/zelta
# Or: brew tap zelta-app/tap && brew install zelta

class Zelta < Formula
  desc "Manage payments, SMS, wallets, and API monetization from the terminal"
  homepage "https://zelta.app"
  url "https://github.com/FinAegis/core-banking-prototype-laravel/releases/download/cli-v0.2.0/zelta.phar"
  sha256 "PLACEHOLDER_SHA256"
  license "Apache-2.0"
  version "0.2.0"

  depends_on "php" => "8.4"

  def install
    bin.install "zelta.phar" => "zelta"
  end

  test do
    assert_match "Zelta CLI", shell_output("#{bin}/zelta --version")
  end
end
