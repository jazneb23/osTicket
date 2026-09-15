import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  canonicalizeHarnessOutput,
  harnessOutputsEqual,
} from "./harness";

describe("canonicalizeHarnessOutput", () => {
  it("passes through ISO timestamp strings unchanged", () => {
    assert.equal(
      canonicalizeHarnessOutput("2024-01-15T12:00:00"),
      "2024-01-15T12:00:00"
    );
  });

  it("passes through already-stringified object output unchanged", () => {
    const encoded = '{"return":false,"isoverdue":0}';
    assert.equal(canonicalizeHarnessOutput(encoded), encoded);
  });

  it("serializes object-shaped Topic harness output", () => {
    assert.equal(
      canonicalizeHarnessOutput({ isActive: true, isEnabled: true }),
      '{"isActive":true,"isEnabled":true}'
    );
  });

  it("maps null and undefined to null", () => {
    assert.equal(canonicalizeHarnessOutput(null), null);
    assert.equal(canonicalizeHarnessOutput(undefined), null);
  });
});

describe("harnessOutputsEqual", () => {
  it("treats equal objects as equal even when they are different references", () => {
    assert.equal(
      harnessOutputsEqual(
        { isActive: true, isEnabled: true },
        { isActive: true, isEnabled: true }
      ),
      true
    );
  });

  it("matches a JSON-string expected against an equivalent object actual", () => {
    assert.equal(
      harnessOutputsEqual(
        { isActive: false, isEnabled: false },
        '{"isActive":false,"isEnabled":false}'
      ),
      true
    );
  });

  it("does not equate object output to an unrelated timestamp string", () => {
    assert.equal(
      harnessOutputsEqual({ isActive: true }, "2024-01-15T12:00:00"),
      false
    );
  });
});
