from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

setup(
    name="finaegis",
    version="1.0.0",
    author="FinAegis",
    author_email="sdk@finaegis.org",
    description="Official Python SDK for the FinAegis API",
    long_description=long_description,
    long_description_content_type="text/markdown",
    url="https://github.com/FinAegis/finaegis-python",
    packages=find_packages(),
    classifiers=[
        "Development Status :: 5 - Production/Stable",
        "Intended Audience :: Developers",
        "Topic :: Software Development :: Libraries :: Python Modules",
        "License :: OSI Approved :: MIT License",
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.7",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
    ],
    python_requires=">=3.7",
    install_requires=[
        "requests>=2.28.0",
        "urllib3>=1.26.0",
        "python-dateutil>=2.8.0",
    ],
    extras_require={
        "dev": [
            "pytest>=7.0",
            "pytest-cov>=4.0",
            "pytest-asyncio>=0.20",
            "black>=22.0",
            "flake8>=5.0",
            "mypy>=0.990",
            "requests-mock>=1.9.0",
        ],
        "async": [
            "aiohttp>=3.8.0",
        ]
    },
    project_urls={
        "Bug Reports": "https://github.com/FinAegis/finaegis-python/issues",
        "Source": "https://github.com/FinAegis/finaegis-python",
        "Documentation": "https://docs.finaegis.org/sdks/python",
    },
)