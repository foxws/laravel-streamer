# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within Laravel Shaka Packager, please send an email to <francoism90@users.noreply.github.com>. All security vulnerabilities will be promptly addressed.

Please do **not** create a public GitHub issue for security vulnerabilities.

### What to Include

When reporting a vulnerability, please include:

- Description of the vulnerability
- Steps to reproduce the issue
- Potential impact
- Any suggested fixes (if applicable)

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Fix Release**: Varies based on severity and complexity

## Security Best Practices

When using this package:

1. **Encryption Keys**: Never commit encryption keys to version control
2. **Temporary Files**: Ensure temporary file paths are properly secured
3. **Input Validation**: Validate and sanitize all user-provided file paths
4. **Access Control**: Implement proper access control for packaged media
5. **Environment Variables**: Use environment variables for sensitive configuration
6. **Disk Permissions**: Ensure proper file system permissions on temporary and output directories

## Known Security Considerations

- The package executes the Shaka Packager binary - ensure the binary is from a trusted source
- Temporary files may contain sensitive media content - use secure directories (e.g., `/dev/shm` for encrypted content)
- Encryption keys are logged as `[REDACTED]` but ensure log files are properly secured
